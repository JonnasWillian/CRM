<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->statefulApi();

        $middleware->alias([
            'tenant' => \App\Http\Middleware\IdentifyTenant::class,
        ]);

        /*
         * O tenant precisa estar ativo ANTES do route model binding.
         *
         * `SubstituteBindings` resolve os models tipados na assinatura da rota
         * — {funil}, {estagio}, {motivo}, {usuario}. Todos usam BelongsToTenant,
         * cujo TenantScope é fail-closed: sem tenant ativo ele lança.
         *
         * Middleware de rota roda depois do grupo, e `SubstituteBindings` vem
         * no grupo. Sem esta linha, o binding acontecia antes de
         * IdentifyTenant e toda rota com parâmetro de model devolvia 500.
         *
         * Os testes não pegavam porque o CurrentTenant é singleton e o
         * setUp() dos casos o deixava preenchido antes da requisição —
         * mascarando exatamente a condição de produção. O teste em
         * tests/Feature/Navegacao/UrlDoLeadTest.php limpa o tenant de
         * propósito para que a regressão não passe de novo.
         */
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\IdentifyTenant::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
