<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Rules\WhatsappNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/** الملف التعريفي للمستخدم الحالي: تعديل البيانات ورفع الصورة */
class MeController extends Controller
{
    // PATCH /me — تعديل بيانات الحساب الشخصية
    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'email' => ['sometimes', 'required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'whatsapp' => ['nullable', new WhatsappNumber],
            'employee_number' => ['nullable', 'string', 'max:30'],
        ]);

        $user->update($data);

        return response()->json($user->fresh()->load(['specialization', 'academicTerm']));
    }

    // POST /me/avatar — رفع صورة الملف التعريفي (تستبدل السابقة)
    public function avatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $old = $user->avatar_path;

        $user->update(['avatar_path' => $request->file('avatar')->store('avatars', 'public')]);

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        return response()->json(['avatar_url' => $user->avatar_url]);
    }
}
