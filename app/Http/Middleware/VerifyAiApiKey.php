<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyAiApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = config('services.ai_api.key');

        if (! $key || ! $request->bearerToken() || ! hash_equals($key, $request->bearerToken())) {
            abort(401, 'Unauthorized.');
        }

        return $next($request);
    }
}
