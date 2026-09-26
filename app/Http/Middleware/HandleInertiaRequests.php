<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
                // Closure, não array pronto: Inertia::share() é chamado por
                // este middleware ANTES de $next($request), e o middleware de
                // rota `tenant` — que define o team do spatie via
                // setPermissionsTeamId() — só roda depois. Avaliar can() aqui e
                // agora consultaria tenant_id = null e devolveria false para
                // todo mundo. A closure é resolvida na montagem da resposta,
                // quando o team já está definido.
                'permissions' => fn () => [
                    'configuracoes.manage' => (bool) $request->user()?->can('configuracoes.manage'),
                    'leads.view-all' => (bool) $request->user()?->can('leads.view-all'),
                ],
            ],
        ];
    }
}
