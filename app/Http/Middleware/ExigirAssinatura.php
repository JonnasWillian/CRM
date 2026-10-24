<?php

namespace App\Http\Middleware;

use App\Services\Assinaturas\Assinaturas;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica a situação da assinatura a cada requisição autenticada.
 *
 * - Teste encerrado, plano vencido depois da carência ou cancelado: a
 *   empresa lê tudo, mas não grava — exceto escolher plano, cuidar da
 *   própria conta e sair.
 * - Trinta dias nesse estado: acesso suspenso. Também a leitura é cortada;
 *   sobram a página de acesso suspenso, planos e faturas, a exportação de
 *   leads (os dados são da empresa), o perfil e sair.
 *
 * Roda nos grupos web e api (antes do tenant ser ativado), por isso
 * consulta a assinatura por tenant_id.
 */
class ExigirAssinatura
{
    private const ROTAS_LIVRES = ['configuracoes.assinatura', 'logout', 'profile.', 'password.', 'verification.'];

    private const LIVRES_NA_SUSPENSAO = ['assinatura.suspensa', 'api.leads.exportar'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! config('comercial.exigir_assinatura') || $user === null || $user->tenant_id === null) {
            return $next($request);
        }

        $rota = (string) $request->route()?->getName();
        if (self::livre($rota, self::ROTAS_LIVRES)) {
            return $next($request);
        }

        $assinatura = app(Assinaturas::class)->da($user->tenant_id);

        if ($assinatura->suspensa() && ! self::livre($rota, self::LIVRES_NA_SUSPENSAO)) {
            $mensagem = 'O acesso da empresa está suspenso por falta de pagamento. Regularize o plano para voltar a usar.';
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => $mensagem, 'assinatura' => 'suspensa'], 402);
            }

            return redirect()->route('assinatura.suspensa');
        }

        if ($request->isMethodSafe() || ! $assinatura->bloqueada()) {
            return $next($request);
        }

        $mensagem = match ($assinatura->situacao()) {
            'vencida' => 'Sua assinatura venceu. Renove o plano para voltar a editar.',
            'cancelada' => 'Sua assinatura foi cancelada. Escolha um plano para voltar a editar.',
            default => 'Seu período de teste terminou. Escolha um plano para continuar editando.',
        };

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $mensagem, 'assinatura' => 'bloqueada'], 402);
        }

        return back()->with('erro', $mensagem);
    }

    /** @param  list<string>  $prefixos */
    private static function livre(string $rota, array $prefixos): bool
    {
        foreach ($prefixos as $prefixo) {
            if ($rota !== '' && str_starts_with($rota, $prefixo)) {
                return true;
            }
        }

        return false;
    }
}
