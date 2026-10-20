<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Enums\UserStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\InviteLink;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteController extends Controller
{
    public function invite(Request $request, User $user)
    {
        abort_unless($request->user()->role === RoleEnum::SuperAdmin, 403);

        // رابط دعوة جديد يُبطل أي روابط سابقة غير مستخدمة لنفس المستخدم (رابط فعّال واحد فقط)
        InviteLink::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $invite = InviteLink::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'expires_at' => now()->addDays(3),
        ]);

        return response()->json([
            'token' => $invite->token,
            'expires_at' => $invite->expires_at,
        ], 201);
    }

    /**
     * فحص حالة رابط الدعوة دون استهلاكه — تستخدمه الواجهة لعرض الحالة الصحيحة
     * (صالح/مستخدم/منتهي) عند فتح الرابط، بما في ذلك وضع التصفح الخفي.
     */
    public function status(string $token)
    {
        $invite = InviteLink::with('user')->where('token', $token)->first();

        if (! $invite) {
            return response()->json(['status' => 'invalid']);
        }

        if ($invite->used_at) {
            return response()->json(['status' => 'used']);
        }

        if ($invite->expires_at->isPast()) {
            return response()->json(['status' => 'expired']);
        }

        if ($invite->user->status === UserStatusEnum::Restricted) {
            return response()->json(['status' => 'restricted']);
        }

        return response()->json(['status' => 'valid']);
    }

    public function accept(Request $request, string $token)
    {
        $invite = InviteLink::with('user')->where('token', $token)->first();

        if (! $invite) {
            abort(410, 'رابط الدعوة غير صالح.');
        }

        if ($invite->used_at) {
            abort(410, 'تم استخدام رابط الدعوة هذا مسبقًا لتعيين كلمة المرور. سجّل/سجّلي الدخول مباشرة بالبريد الإلكتروني وكلمة المرور، أو اطلب/اطلبي رابط دعوة جديد من المشرف.');
        }

        if ($invite->expires_at->isPast()) {
            abort(410, 'انتهت صلاحية رابط الدعوة (٣ أيام من إصداره). اطلب/اطلبي رابط دعوة جديد من المشرف.');
        }

        // الحساب الموقوف لا يُفعَّل عبر رابط الدعوة/إعادة التعيين (لا تُعيَّن كلمة مرور ولا يُصدَر توكن دخول)
        abort_if($invite->user->status === UserStatusEnum::Restricted, 403, 'تم إيقاف هذا الحساب، يرجى التواصل مع العمادة.');

        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // رابط الدعوة مرتبط ببريد صاحبه: لا يُقبل إلا بإدخال البريد المطابق — فلا يكفي امتلاك الرابط وحده
        if (mb_strtolower(trim($data['email'])) !== mb_strtolower($invite->user->email)) {
            throw ValidationException::withMessages([
                'email' => 'البريد الإلكتروني لا يطابق صاحب رابط الدعوة.',
            ]);
        }

        $invite->user->update([
            'password' => $data['password'],
            'must_change_password' => false,
        ]);

        // استخدام لمرة واحدة: إبطال هذا الرابط وكل روابط الدعوة غير المستخدمة لنفس المستخدم معًا
        InviteLink::where('user_id', $invite->user_id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $accessToken = $invite->user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $invite->user->fresh(),
            'token' => $accessToken,
        ]);
    }
}
