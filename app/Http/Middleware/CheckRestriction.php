<?php

namespace App\Http\Middleware;

use App\Enums\AccessLevelEnum;
use App\Support\Rbac\AccessControl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRestriction
{
    public function __construct(private AccessControl $accessControl) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $level = $this->accessControl->can($request->user(), $module);

        if ($level === AccessLevelEnum::Blocked) {
            abort(403, 'تم تقييد صلاحيتك لهذه الوحدة، يرجى التواصل مع العمادة.');
        }

        $request->attributes->set('access_level', $level);

        return $next($request);
    }
}
