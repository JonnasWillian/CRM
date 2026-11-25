<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cabeçalhos de segurança em toda resposta: o app não pode ser embutido em
 * outro site (clickjacking), o navegador não adivinha tipo de arquivo, o
 * endereço completo não vaza para sites de fora e, em produção com https,
 * o navegador passa a exigir https (HSTS).
 *
 * A CSP aqui só cobre moldura, <base> e plugins. Restringir scripts exige
 * nonce nos scripts inline do app.blade.php e do Ziggy: fica para depois.
 */
class CabecalhosDeSeguranca
{
    public function handle(Request $request, Closure $next): Response
    {
        $resposta = $next($request);

        $resposta->headers->set('X-Frame-Options', 'DENY');
        $resposta->headers->set('X-Content-Type-Options', 'nosniff');
        $resposta->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $resposta->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $resposta->headers->set('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
        if (app()->isProduction() && $request->isSecure()) {
            $resposta->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $resposta;
    }
}
