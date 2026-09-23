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

        $tenant = $user->tenant;

        if ($tenant === null) {
            // tenant_id aponta para um Tenant soft-deleted (SoftDeletes em
            // Tenant) — a relação não resolve mais, mesmo com tenant_id
            // não-nulo. Sem este check, CurrentTenant::set()'s type hint
            // (Tenant $tenant) recebe null e lança TypeError -> 500 não
            // tratado, em vez de um 403 previsível.
            abort(403, 'Usuário sem tenant associado.');
        }

        app(\App\Support\Tenancy\CurrentTenant::class)->set($tenant);

        // O spatie/laravel-permission está em modo teams, com `tenant_id` como
        // chave do team. Sem definir o team ativo, toda verificação de
        // permissão consultaria `tenant_id = null` e não encontraria as
        // atribuições, que são por empresa.
        setPermissionsTeamId($tenant->id);

        // Exigido pela documentação do pacote: relações de papel/permissão
        // resolvidas ANTES da troca de team ficam em cache no próprio model, e
        // a verificação seguinte leria o team errado. Um mesmo processo de
        // fila ou de teste atende requisições de tenants diferentes.
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $next($request);
    }
}
