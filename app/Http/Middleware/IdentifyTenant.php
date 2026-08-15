<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            abort(401);
        }

        $user = auth()->user();

        if ($user->tenant_id === null) {
            abort(403, 'Usuário sem tenant associado.');
        }

        app(\App\Support\Tenancy\CurrentTenant::class)->set($user->tenant);

        return $next($request);
    }
}
