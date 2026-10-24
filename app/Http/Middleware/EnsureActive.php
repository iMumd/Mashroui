<?php

namespace App\Http\Middleware;

use App\Enums\UserStatusEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يمنع المستخدم الموقوف (status = restricted) من استخدام المنصة فعليًا على كل طلب محمي،
 * حتى لو كان يملك توكنًا قديمًا. تسجيل الدخول نفسه يُرفض في AuthController، وهذا حارس إضافي.
 */
class EnsureActive
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->status === UserStatusEnum::Restricted) {
            abort(403, 'تم إيقاف هذا الحساب، يرجى التواصل مع العمادة.');
        }

        return $next($request);
    }
}
