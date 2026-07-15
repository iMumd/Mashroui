<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Rbac\AccessControl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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
