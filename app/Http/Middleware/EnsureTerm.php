<?php

namespace App\Http\Middleware;

use App\Support\CurrentTerm;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTerm
{
    public function __construct(private CurrentTerm $currentTerm) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $termId = $request->integer('term_id') ?: $request->user()?->term_id;

        $this->currentTerm->set($termId);

        return $next($request);
    }
}
