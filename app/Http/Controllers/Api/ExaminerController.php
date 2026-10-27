<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\Examiner;
use App\Rules\WhatsappNumber;
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
