<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccessLevelEnum;
use App\Enums\GenderEnum;
use App\Enums\RegionEnum;
use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\InviteLink;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\UserRestriction;
use App\Rules\CollegeEmail;
use App\Rules\WhatsappNumber;
use App\Support\CurrentTerm;
use App\Support\Rbac\AccessControl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request, CurrentTerm $currentTerm)
    {
        $actor = $request->user();

        if ($actor->role === RoleEnum::SuperAdmin) {
            $allowedRoles = [RoleEnum::Student->value, RoleEnum::Supervisor->value, RoleEnum::Committee->value];
        } elseif ($actor->role === RoleEnum::Committee) {
            // أعضاء اللجنة للقراءة فقط: مستلمو التكليفات والإعلانات (الإنشاء والتعديل للإدارة العامة)
            $allowedRoles = [RoleEnum::Student->value, RoleEnum::Supervisor->value, RoleEnum::Committee->value];
        } else {
            abort(403);
        }

        $data = $request->validate([
            'role' => ['required', Rule::in($allowedRoles)],
            'unassigned' => ['sometimes', 'boolean'],
            'gender' => ['sometimes', Rule::enum(GenderEnum::class)],
            'region' => ['sometimes', Rule::enum(RegionEnum::class)],
            'specialization_id' => ['sometimes', 'integer', 'exists:specializations,id'],
            'search' => ['sometimes', 'string', 'max:100'],
        ]);

        $termId = $currentTerm->get();

        // كل الحسابات (الطلاب والمشرفون ولجنة الإشراف) مرتبطة بالفصل الدراسي:
        // الفصل الجديد يبدأ بقوائم فارغة ويُضاف له مشرفوه ولجنته، وفصل سابق لا يظهر في الحالي.
        $query = User::where('role', $data['role'])->where('term_id', $termId);

        if ($data['role'] === RoleEnum::Student->value && $request->boolean('unassigned')) {
            $assignedIds = TeamMember::whereHas('team', fn ($q) => $q->where('term_id', $termId))->pluck('student_id');
            $query->whereNotIn('id', $assignedIds);
        }

        // تصنيف حسب الجنس (طلاب/طالبات) ومكان التواجد (غزة/جنوب)
        if (isset($data['gender'])) {
            $query->where('gender', $data['gender']);
        }
        if (isset($data['region'])) {
            $query->where('region', $data['region']);
        }

        // عضو اللجنة المرتبط بتخصص (أو تخصصين): الطلاب والمشرفون من تخصصاته فقط
        $viewer = $request->user();
        $specIds = null;
        if ($viewer->role === RoleEnum::Committee && in_array($data['role'], [RoleEnum::Student->value, RoleEnum::Supervisor->value], true)) {
            $specIds = $viewer->effectiveSpecializationIds();
        } elseif (isset($data['specialization_id'])) {
            $specIds = [$data['specialization_id']];
        }

        // التخصص: تخصص الحساب، وللمشرف أيضًا تخصصات المجموعات التي يشرف عليها
        if (! empty($specIds)) {
            $query->where(fn ($q) => $q->whereIn('specialization_id', $specIds)
                ->orWhereHas('supervisedTeams', fn ($t) => $t->whereIn('specialization_id', $specIds)));
        }

        // بحث بالاسم أو الرقم الجامعي/الوظيفي أو البريد
        if (! empty($data['search'])) {
            $term = '%'.$data['search'].'%';
            $query->where(fn ($q) => $q->where('name', 'like', $term)
                ->orWhere('university_number', 'like', $term)
                ->orWhere('employee_number', 'like', $term)
                ->orWhere('email', 'like', $term));
        }

        // للمشرفين: عدد مهام فرقهم وآخر نشاط (آخر استخدام لجلسة الدخول)
        if ($data['role'] === RoleEnum::Supervisor->value) {
            $query->withCount('supervisedTasks')->withMax('tokens', 'last_used_at');
        }

        $users = $query->with('specializations', 'departments')->orderBy('name')->get();

        return UserResource::collection($users);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeManageTarget($request, $user);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'whatsapp' => ['nullable', new WhatsappNumber],
            'employee_number' => ['nullable', 'string', 'max:30'],
            // الرقم الجامعي للطالب لا يُفرَّغ بعد ضبطه (إجباري عند إرساله لطالب)
            'university_number' => $user->role === RoleEnum::Student
                ? ['sometimes', 'required', 'string', 'max:30']
                : ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', Rule::enum(GenderEnum::class)],
            'region' => ['nullable', Rule::enum(RegionEnum::class)],
            'specialization_id' => ['nullable', 'exists:specializations,id'],
            'specialization_ids' => ['sometimes', 'nullable', 'array', 'max:3'],
            'specialization_ids.*' => ['integer', 'exists:specializations,id'],
            'department_ids' => ['sometimes', 'nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ]);

        $syncSpecs = $request->has('specialization_ids');
        $specIds = $syncSpecs
            ? array_values(array_unique(array_map('intval', $data['specialization_ids'] ?? [])))
            : null;
        unset($data['specialization_ids']);

        $syncDepts = $request->has('department_ids');
        $deptIds = $syncDepts
            ? array_values(array_unique(array_map('intval', $data['department_ids'] ?? [])))
            : null;
        unset($data['department_ids']);

        // عضو اللجنة لازم نطاق واحد على الأقل: تخصص محدد أو قسم كامل
        if ($user->role === RoleEnum::Committee && ($syncSpecs || $syncDepts)) {
            $effSpecs = $syncSpecs ? count($specIds) : $user->specializations()->count();
            $effDepts = $syncDepts ? count($deptIds) : $user->departments()->count();
            if ($effSpecs < 1 && $effDepts < 1) {
                throw ValidationException::withMessages(['specialization_ids' => 'عضو اللجنة يجب ربطه بتخصص أو قسم واحد على الأقل.']);
            }
        }

        if ($syncSpecs) {
            $data['specialization_id'] = $specIds[0] ?? null;
        }

        $user->update($data);

        if ($syncSpecs) {
            $user->specializations()->sync($specIds);
        }
        if ($syncDepts) {
            $user->departments()->sync($deptIds);
        }
        if ($syncSpecs || $syncDepts) {
            $user->effectiveSpecializationIdsCache = null;
        }

        return new UserResource($user);
    }

    public function updateStatus(Request $request, User $user)
    {
        $this->authorizeManageTarget($request, $user);

        $data = $request->validate([
            'status' => ['required', Rule::in([UserStatusEnum::Active->value, UserStatusEnum::Restricted->value])],
            'reason' => ['required_if:status,restricted', 'nullable', 'string', 'max:500'],
        ]);

        $isRestricting = $data['status'] === UserStatusEnum::Restricted->value;

        $user->update([
            'status' => $data['status'],
            'restricted_reason' => $isRestricting ? $data['reason'] : null,
        ]);

        // إيقاف الدخول يقطع الجلسات الشغّالة فورًا: حذف توكنات المستخدم كلها حتى لا يُكمل استخدام المنصة بتوكن قديم
        if ($isRestricting) {
            $user->tokens()->delete();
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $isRestricting ? 'restrict' : 'unrestrict',
            'entity' => 'user',
            'entity_id' => $user->id,
            'meta' => $isRestricting ? ['reason' => $data['reason']] : [],
        ]);

        return new UserResource($user);
    }

    public function destroy(Request $request, User $user)
    {
        $this->authorizeManageTarget($request, $user);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $user->update(['deleted_reason' => $data['reason']]);
        $user->delete();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'delete',
            'entity' => 'user',
            'entity_id' => $user->id,
            'meta' => ['name' => $user->name, 'reason' => $data['reason']],
        ]);

        return response()->json(null, 204);
    }

    public function trashed(Request $request, CurrentTerm $currentTerm)
    {
        $actor = $request->user();

        if ($actor->role === RoleEnum::SuperAdmin) {
            $allowedRoles = [RoleEnum::Student->value, RoleEnum::Supervisor->value, RoleEnum::Committee->value];
        } elseif ($actor->role === RoleEnum::Committee) {
            $allowedRoles = [RoleEnum::Student->value, RoleEnum::Supervisor->value];
        } else {
            abort(403);
        }

        $data = $request->validate([
            'role' => ['required', Rule::in($allowedRoles)],
        ]);

        // المحذوفون أيضًا محصورون بالفصل الحالي حتى لا يظهر أرشيف فصل آخر
        $users = User::onlyTrashed()
            ->where('role', $data['role'])
            ->where('term_id', $currentTerm->get())
            ->orderBy('name')
            ->get();

        return UserResource::collection($users);
    }

    public function restore(Request $request, int $user)
    {
        $user = User::onlyTrashed()->findOrFail($user);

        $this->authorizeManageTarget($request, $user);

        $user->update(['deleted_reason' => null]);
        $user->restore();

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'restore',
            'entity' => 'user',
            'entity_id' => $user->id,
            'meta' => ['name' => $user->name],
        ]);

        return new UserResource($user);
    }

    public function setPassword(Request $request, User $user)
    {
        $this->authorizeManageTarget($request, $user);

        $password = $this->generateMemberPassword($user);

        $user->update([
            'password' => $password,
            'must_change_password' => true,
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'set_password',
            'entity' => 'user',
            'entity_id' => $user->id,
            'meta' => [],
        ]);

        return response()->json(['password' => $password]);
    }

    /**
     * كلمة سر عشوائية بتُرسل نصًا (واتساب/بريد) وبيُعاد كتابتها يدويًا غالبًا — نتفادى قصدًا أي رمز
     * ممكن يلخبط النقل اليدوي: بلا رموز خاصة إطلاقًا (ولا حتى أحرف تنسيق واتساب _ * ~ `)،
     * وبلا أحرف متشابهة بصريًا (0/O، 1/l/I) — بس حروف وأرقام واضحة 100%.
     */
    private function generateSafePassword(int $length = 14): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $password;
    }

    /**
     * كلمة سر بسيطة وسهلة التمرير يدويًا: اسم المستخدم من البريد + 5 أرقام عشوائية + اللاحقة !@#
     * مثال: malghoul@smail.ucas.edu.ps -> malghoul48213!@#
     */
    private function generateMemberPassword(User $user): string
    {
        $local = strtolower((string) strstr((string) $user->email, '@', true));
        $local = preg_replace('/[^a-z0-9]/', '', $local) ?: 'user';
        $digits = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);

        return $local.$digits.'!@#';
    }

    /**
     * سوبر أدمن يدير حسابات المشرفين ولجنة الإشراف، ولجنة الإشراف تدير حسابات الطلاب والمشرفين
     * (تواصل/إيقاف فقط — كل دور محصور بالأدوار اللي فعليًا يشرف عليها).
     */
    private function authorizeManageTarget(Request $request, User $user): void
    {
        $actor = $request->user();

        if ($actor->role === RoleEnum::SuperAdmin) {
            abort_unless(in_array($user->role, [RoleEnum::Supervisor, RoleEnum::Committee, RoleEnum::TeamLeader, RoleEnum::Student], true), 404);

            return;
        }

        if ($actor->role === RoleEnum::Committee) {
            abort_unless(in_array($user->role, [RoleEnum::Student, RoleEnum::TeamLeader, RoleEnum::Supervisor], true), 404);

            return;
        }

        abort(403);
    }

    /**
     * سوبر أدمن ينشئ حسابات مشرفين ولجنة إشراف. لجنة الإشراف تنشئ حسابات مشرفين وطلاب
     * (طلاب أيضًا عبر استيراد Excel الجماعي — هذا المسار لإضافة حساب واحد مباشرة).
     */
    public function store(Request $request, CurrentTerm $currentTerm)
    {
        $actor = $request->user();

        if ($actor->role === RoleEnum::SuperAdmin) {
            $allowedRoles = [RoleEnum::Supervisor->value, RoleEnum::Committee->value, RoleEnum::Student->value];
        } elseif ($actor->role === RoleEnum::Committee) {
            $allowedRoles = [RoleEnum::Supervisor->value, RoleEnum::Student->value];
        } else {
            abort(403);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'role' => ['required', Rule::in($allowedRoles)],
            'whatsapp' => ['nullable', new WhatsappNumber],
            // الرقم الوظيفي إجباري للمشرف (معرّفه الرسمي في الكلية)
            'employee_number' => ['nullable', 'string', 'max:30', 'required_if:role,'.RoleEnum::Supervisor->value],
            // الرقم الجامعي إجباري للطالب (معرّفه الرسمي في الكلية)
            'university_number' => ['nullable', 'string', 'max:30', 'required_if:role,'.RoleEnum::Student->value],
            // جنس الطالب إجباري حتى يظهر في قائمة الطلاب/الطالبات الصحيحة (التصنيف يعتمد عليه)
            'gender' => ['nullable', Rule::enum(GenderEnum::class), 'required_if:role,'.RoleEnum::Student->value],
            'region' => ['nullable', Rule::enum(RegionEnum::class), 'required_if:role,'.RoleEnum::Student->value],
            // عضو اللجنة لا بد أن يُربط بنطاق واحد على الأقل: حتى 3 تخصصات محددة أو أقسام كاملة
            // (SpecializationScope يعمل عند وجود تخصصات فعّالة، وأقسام العضو تُوسَّع إلى كل تخصصاتها).
            'specialization_id' => ['nullable', 'exists:specializations,id'],
            'specialization_ids' => ['nullable', 'array', 'max:3'],
            'specialization_ids.*' => ['integer', 'exists:specializations,id'],
            'department_ids' => ['nullable', 'array'],
            'department_ids.*' => ['integer', 'exists:departments,id'],
        ], [
            'gender.required_if' => 'جنس الطالب (طالب/طالبة) مطلوب.',
            'region.required_if' => 'مكان تواجد الطالب مطلوب.',
            'employee_number.required_if' => 'الرقم الوظيفي للمشرف مطلوب.',
            'university_number.required_if' => 'الرقم الجامعي للطالب مطلوب.',
        ]);

        $expectedDomain = $data['role'] === RoleEnum::Student->value ? CollegeEmail::STUDENT_DOMAIN : CollegeEmail::STAFF_DOMAIN;
        if (! CollegeEmail::matches($data['email'], $expectedDomain)) {
            throw ValidationException::withMessages(['email' => "البريد الإلكتروني يجب أن ينتهي بـ @{$expectedDomain}."]);
        }

        $specIds = ! empty($data['specialization_ids'])
            ? $data['specialization_ids']
            : (isset($data['specialization_id']) ? [$data['specialization_id']] : []);
        $specIds = array_values(array_unique(array_map('intval', $specIds)));

        $deptIds = array_values(array_unique(array_map('intval', $data['department_ids'] ?? [])));

        if ($data['role'] === RoleEnum::Committee->value && count($specIds) < 1 && count($deptIds) < 1) {
            throw ValidationException::withMessages(['specialization_ids' => 'عضو اللجنة يجب ربطه بتخصص أو قسم واحد على الأقل.']);
        }

        // عضو اللجنة المرتبط بتخصص: الطلاب والمشرفون الذين يضيفهم يُربطون بأول تخصص من تخصصاته تلقائيًا
        // حتى يظهروا له في قائمة الأعضاء ويتمكّن من إدارتهم (قراءة اللجنة مقيّدة بتخصصاتها في index()).
        if ($actor->role === RoleEnum::Committee
            && in_array($data['role'], [RoleEnum::Student->value, RoleEnum::Supervisor->value], true)) {
            $actorSpecIds = $actor->effectiveSpecializationIds();
            $specIds = $actorSpecIds ? [$actorSpecIds[0]] : [];
        }

        $data['specialization_id'] = $specIds[0] ?? null;

        $termId = $currentTerm->get();

        if (! $termId) {
            throw ValidationException::withMessages(['term_id' => 'لا يوجد سنة دراسية محدّدة.']);
        }

        $result = DB::transaction(function () use ($data, $termId, $specIds, $deptIds, $actor) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(32),
                'role' => $data['role'],
                'whatsapp' => $data['whatsapp'] ?? null,
                'employee_number' => $data['employee_number'] ?? null,
                'university_number' => $data['university_number'] ?? null,
                'gender' => $data['gender'] ?? null,
                'region' => $data['region'] ?? null,
                'specialization_id' => $data['specialization_id'] ?? null,
                'term_id' => $termId,
                'status' => UserStatusEnum::Active,
                'must_change_password' => true,
            ]);

            if ($specIds) {
                $user->specializations()->sync($specIds);
            }
            if ($deptIds) {
                $user->departments()->sync($deptIds);
            }

            // الصلاحية الافتراضية للمشرف عند الإنشاء = مشاهدة فقط على كل الوحدات القابلة للتقييد،
            // والسوبر أدمن (أو اللجنة لاحقًا) يرفع الصلاحية من إدارة القيود.
            if ($user->role === RoleEnum::Supervisor) {
                foreach (AccessControl::MODULES as $module) {
                    UserRestriction::updateOrCreate(
                        ['user_id' => $user->id, 'module' => $module],
                        ['level' => AccessLevelEnum::ViewOnly, 'reason' => 'صلاحية افتراضية عند إنشاء الحساب — مشاهدة فقط', 'restricted_by' => $actor->id],
                    );
                }
            }

            $invite = InviteLink::create([
                'user_id' => $user->id,
                'token' => Str::random(64),
                'expires_at' => now()->addDays(3),
            ]);

            return ['user' => $user, 'invite_token' => $invite->token, 'expires_at' => $invite->expires_at];
        });

        return response()->json($result, 201);
    }
}
