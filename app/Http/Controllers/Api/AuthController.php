<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatusEnum;
use App\Http\Controllers\Controller;
use App\Jobs\SendRelayEmail;
use App\Models\InviteLink;
use App\Models\User;
use App\Support\Rbac\AccessControl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            $invite = InviteLink::create([
                'user_id' => $user->id,
                'token' => Str::random(64),
                'expires_at' => now()->addHours(2),
            ]);

            $url = rtrim(config('app.frontend_url'), '/').'/reset-password?token='.$invite->token;

            SendRelayEmail::dispatch(
                $user->email,
                'إعادة تعيين كلمة المرور - '.config('app.name'),
                "مرحبًا {$user->name}،\n\nوصلنا طلب لإعادة تعيين كلمة مرورك على منصة ".config('app.name').".\n"
                    ."اضغط/اضغطي الرابط التالي لتعيين كلمة مرور جديدة (صالح لمدة ساعتين):\n{$url}\n\n"
                    .'إذا لم تطلب/تطلبي هذا، تجاهل/تجاهلي هذه الرسالة.'
            );
        }

        // ما منفصح إذا البريد مسجّل عنا أو لأ — حماية من تسريب معلومة وجود الحساب
        return response()->json([
            'message' => 'إذا كان البريد الإلكتروني مسجّلاً لدينا، ستصلك رسالة تحتوي رابط إعادة تعيين كلمة المرور.',
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'بيانات الدخول غير صحيحة.',
            ]);
        }

        if ($user->status === UserStatusEnum::Restricted) {
            throw ValidationException::withMessages([
                'email' => 'تم إيقاف هذا الحساب، يرجى التواصل مع العمادة.',
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load(['specialization', 'academicTerm']));
    }

    public function abilities(Request $request, AccessControl $accessControl)
    {
        $user = $request->user();

        $abilities = collect(AccessControl::MODULES)
            ->mapWithKeys(fn (string $module) => [$module => $accessControl->can($user, $module)->value]);

        return response()->json($abilities);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();
        $user->update([
            'password' => $data['password'],
            'must_change_password' => false,
        ]);

        return response()->json(['message' => 'تم تحديث كلمة المرور.']);
    }
}
