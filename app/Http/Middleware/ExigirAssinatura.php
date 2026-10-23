<?php

namespace App\Http\Middleware;

use App\Services\Assinaturas\Assinaturas;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Teste expirado, plano vencido ou cancelado: a empresa continua lendo
 * tudo, mas não grava mais — exceto para escolher um plano, cuidar da
 * própria conta e sair. Roda nos grupos web e api (antes do tenant ser
 * ativado), por isso consulta a assinatura por tenant_id.
 */
class ExigirAssinatura
{
    private const ROTAS_LIVRES = ['configuracoes.assinatura', 'logout', 'profile.', 'password.', 'verification.'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! config('comercial.exigir_assinatura') || $user === null || $user->tenant_id === null || $request->isMethodSafe()) {
            return $next($request);
        }

        $rota = (string) $request->route()?->getName();
        foreach (self::ROTAS_LIVRES as $prefixo) {
            if (str_starts_with($rota, $prefixo)) {
                return $next($request);
            }
        }

        $assinatura = app(Assinaturas::class)->da($user->tenant_id);
        if (! $assinatura->bloqueada()) {
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
}
