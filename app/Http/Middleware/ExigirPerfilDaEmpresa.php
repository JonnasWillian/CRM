<?php

namespace App\Http\Middleware;

use App\Models\PerfilDeEmpresa;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Leva o admin da empresa recém-cadastrada ao questionário de primeiro
 * acesso enquanto ele não for respondido ou pulado. Só páginas (GET, sem
 * JSON); conta, senha, assinatura e o próprio questionário ficam livres.
 * Empresa sem linha de perfil (anterior ao questionário) nunca é levada.
 * Resolvido, a sessão lembra e o banco não é mais consultado.
 */
class ExigirPerfilDaEmpresa
{
    private const ROTAS_LIVRES = ['boas-vindas.', 'logout', 'profile.', 'password.', 'verification.', 'configuracoes.assinatura', 'assinatura.suspensa'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || $user->tenant_id === null || ! $request->isMethod('GET') || $request->expectsJson() || $request->is('api/*')
            || $request->session()->get('perfil_resolvido')) {
            return $next($request);
        }

        $rota = (string) $request->route()?->getName();
        foreach (self::ROTAS_LIVRES as $livre) {
            if ($rota === $livre || str_starts_with($rota, $livre)) {
                return $next($request);
            }
        }

        if (! PerfilDeEmpresa::pendente($user->tenant_id)) {
            $request->session()->put('perfil_resolvido', true);

            return $next($request);
        }

        // O middleware de rota `tenant` ainda não rodou: o team das permissões vem daqui.
        setPermissionsTeamId($user->tenant_id);
        if (! $user->can('configuracoes.manage')) {
            return $next($request);
        }

        return redirect()->route('boas-vindas.show');
    }
}
