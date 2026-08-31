<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MagicLoginLink;

/**
 * دخول مباشر عبر رابط/QR ثابت لحساب محدّد مسبقًا — لأغراض العرض التقديمي والمناقشة فقط.
 * الرابط قابل لإعادة الاستخدام (بلا used_at) لأنه يُفتح أكثر من مرة أثناء العرض.
 */
class MagicLoginController extends Controller
{
    public function consume(string $token)
    {
        $link = MagicLoginLink::where('token', $token)->first();

        if (! $link) {
            abort(404, 'رابط الدخول غير صالح.');
        }

        if ($link->expires_at && $link->expires_at->isPast()) {
            abort(410, 'انتهت صلاحية رابط الدخول.');
        }

        $accessToken = $link->user->createToken('magic-login')->plainTextToken;

        return response()->json([
            'user' => $link->user,
            'token' => $accessToken,
        ]);
    }
}
