<?php

namespace App\Http\Controllers\Api;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Models\InviteLink;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InviteController extends Controller
{
    public function invite(Request $request, User $user)
    {
        abort_unless($request->user()->role === RoleEnum::SuperAdmin, 403);

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

    public function accept(Request $request, string $token)
    {
        $invite = InviteLink::where('token', $token)->first();

        if (! $invite || $invite->used_at || $invite->expires_at->isPast()) {
            abort(410, 'رابط الدعوة منتهي أو غير صالح.');
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $invite->user->update([
            'password' => $data['password'],
            'must_change_password' => false,
        ]);

        $invite->update(['used_at' => now()]);

        $accessToken = $invite->user->createToken('api')->plainTextToken;

        return response()->json([
            'user' => $invite->user,
            'token' => $accessToken,
        ]);
    }
}
