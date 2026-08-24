<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\InviteLink;
use App\Models\TeamMember;
use App\Models\User;
use App\Rules\WhatsappNumber;
use App\Support\CurrentTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request, CurrentTerm $currentTerm)
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
            'unassigned' => ['sometimes', 'boolean'],
        ]);

        $query = User::where('role', $data['role']);

        if ($data['role'] === RoleEnum::Student->value && $request->boolean('unassigned')) {
            $termId = $currentTerm->get();
            $assignedIds = TeamMember::whereHas('team', fn ($q) => $q->where('term_id', $termId))->pluck('student_id');
            $query->whereNotIn('id', $assignedIds);
        }

        $users = $query->orderBy('name')->get();

        return UserResource::collection($users);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeManageTarget($request, $user);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'whatsapp' => ['nullable', new WhatsappNumber],
            'employee_number' => ['nullable', 'string', 'max:30'],
            'university_number' => ['nullable', 'string', 'max:30'],
            'specialization_id' => ['nullable', 'exists:specializations,id'],
        ]);

        $user->update($data);

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

    public function trashed(Request $request)
    {
        $actor = $request->user();

        if ($actor->role === RoleEnum::SuperAdmin) {
            $allowedRoles = [RoleEnum::Supervisor->value, RoleEnum::Committee->value];
        } elseif ($actor->role === RoleEnum::Committee) {
            $allowedRoles = [RoleEnum::Student->value, RoleEnum::Supervisor->value];
        } else {
            abort(403);
        }

        $data = $request->validate([
            'role' => ['required', Rule::in($allowedRoles)],
        ]);

        $users = User::onlyTrashed()->where('role', $data['role'])->orderBy('name')->get();

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

        $password = Str::password(12);

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
    public function store(Request $request)
    {
        $actor = $request->user();

        if ($actor->role === RoleEnum::SuperAdmin) {
            $allowedRoles = [RoleEnum::Supervisor->value, RoleEnum::Committee->value];
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
            'employee_number' => ['nullable', 'string', 'max:30'],
            'university_number' => ['nullable', 'string', 'max:30'],
            'specialization_id' => ['nullable', 'exists:specializations,id'],
        ]);

        $result = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Str::random(32),
                'role' => $data['role'],
                'whatsapp' => $data['whatsapp'] ?? null,
                'employee_number' => $data['employee_number'] ?? null,
                'university_number' => $data['university_number'] ?? null,
                'specialization_id' => $data['specialization_id'] ?? null,
                'status' => UserStatusEnum::Active,
                'must_change_password' => true,
            ]);

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
