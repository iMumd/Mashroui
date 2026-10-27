<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Examiner;
use App\Models\User;
use App\Rules\WhatsappNumber;
use App\Support\CurrentTerm;
use Illuminate\Http\Request;

/**
 * إدارة المناقشين الخارجيين الدائمين (ليسوا مشرفين ولا لجنة) — لجنة الإشراف فقط،
 * توافقًا مع صلاحية جدولة المناقشات (DiscussionPolicy::create).
 */
class ExaminerController extends Controller
{
    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->role === RoleEnum::Committee, 403);
    }

    // GET /examiners — المناقشون الخارجيون الفعّالون لقائمة الاختيار
    public function index(Request $request)
    {
        $this->authorizeManage($request);

        return response()->json(
            Examiner::where('active', true)->orderBy('name')->get()
        );
    }

    /**
     * GET /examiners/pool — مجمَّع المناقشين المتاحين للاختيار في جدول المناقشة:
     * كل المشرفين وكل أعضاء لجنة الإشراف (في الفصل الحالي) + المناقشون الخارجيون،
     * مع استثناء مشرف المشروع (لا يناقش مجموعته). الاسم هو ما يُضاف لنص لجنة المناقشة.
     */
    public function pool(Request $request, CurrentTerm $currentTerm)
    {
        $this->authorizeManage($request);

        $excludeSupervisorId = $request->integer('exclude_supervisor_id') ?: null;
        $termId = $currentTerm->get();

        $staff = User::query()
            ->whereIn('role', [RoleEnum::Supervisor, RoleEnum::Committee])
            ->where('term_id', $termId)
            ->when($excludeSupervisorId, fn ($q) => $q->where('id', '!=', $excludeSupervisorId))
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $supervisors = $staff->where('role', RoleEnum::Supervisor)
            ->map(fn (User $u) => ['key' => 'sup-'.$u->id, 'user_id' => $u->id, 'name' => $u->name, 'group' => 'المشرفون'])
            ->values();

        $committee = $staff->where('role', RoleEnum::Committee)
            ->map(fn (User $u) => ['key' => 'com-'.$u->id, 'user_id' => $u->id, 'name' => $u->name, 'group' => 'لجنة الإشراف'])
            ->values();

        $externals = Examiner::where('active', true)->orderBy('name')->get()
            ->map(fn (Examiner $e) => ['key' => 'ext-'.$e->id, 'examiner_id' => $e->id, 'name' => $e->displayName(), 'group' => 'مناقشون خارجيون'])
            ->values();

        return response()->json([
            'supervisors' => $supervisors,
            'committee' => $committee,
            'externals' => $externals,
        ]);
    }

    // POST /examiners — إضافة مناقش خارجي جديد
    public function store(Request $request)
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'title' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', new WhatsappNumber],
        ]);

        return response()->json(Examiner::create($data), 201);
    }

    // DELETE /examiners/{examiner} — تعطيل المناقش (يبقى اسمه في المناقشات السابقة)
    public function destroy(Request $request, Examiner $examiner)
    {
        $this->authorizeManage($request);

        $examiner->update(['active' => false]);

        return response()->json(null, 204);
    }
}
