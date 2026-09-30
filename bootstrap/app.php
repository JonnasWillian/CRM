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

        // Usa o limiter 'api' definido no AppServiceProvider.
        $middleware->throttleApi();

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
        /*
         * Um 404 de autorização e um 404 de inexistência precisam ser
         * indistinguíveis.
         *
         * Quatro caminhos distintos produziam quatro corpos distintos, e a
         * diferença entre eles enumera os ids que existem:
         *
         *   binding não achou  -> {"message":"No query results for model [App\\Models\\Usuario] 999999"}
         *   policy negou       -> {"message":"Not Found"}
         *   failedAuthorization-> {"message":""}
         *   catch(\Exception)  -> {"error":"Modelo não encontrado"}
         *
         * O primeiro ainda vaza a classe do model e o id pedido mesmo com
         * APP_DEBUG=false. Um vendedor classificava a carteira inteira de um
         * colega pelo corpo, sem sequer olhar o status.
         *
         * ── Por que a decisão é pelo STATUS e não pela classe da exceção ──
         *
         * `Handler::render()` chama `prepareException()` ANTES dos callbacks
         * registrados aqui (vendor/laravel/framework/.../Exceptions/Handler.php:584-588
         * na 11.36.1). Quando este closure roda, a exceção original já foi
         * traduzida:
         *
         *   ModelNotFoundException            -> NotFoundHttpException
         *   AuthorizationException com status -> HttpException(404, ...) genérica
         *
         * Ou seja: testar `$e instanceof AuthorizationException` nunca casaria,
         * e `instanceof NotFoundHttpException` deixaria passar justamente o 404
         * das policies — `new HttpException(404)` NÃO é um NotFoundHttpException.
         * Por isso a checagem é `HttpExceptionInterface::getStatusCode() === 404`.
         * Os dois ramos seguintes do match são rede de segurança, para o caso de
         * alguém passar a chamar este callback antes do prepareException.
         *
         * `Response::denyAsNotFound()` foi conferido: produz uma
         * AuthorizationException com `status() === 404` e mensagem nula, que
         * vira `HttpException(404, 'Not Found')`.
         *
         * O que NÃO é afetado, de propósito: 403 de permissão de classe (não é
         * 404, cai no `null` e segue o caminho padrão) e as páginas HTML do
         * Inertia (barradas pelo `expectsJson()` — visita Inertia manda
         * `Accept: text/html`, então continua recebendo a página de erro).
         *
         * ── Armadilha para quem for escrever `abort(404, 'mensagem')` ──
         *
         * Este normalizador substitui QUALQUER 404 por
         * `{"message":"Not Found"}` — inclusive um `abort(404, '...')` escrito
         * de propósito, com uma mensagem que faria sentido devolver. Hoje não
         * existe nenhum no app, mas se um dia existir, a mensagem escrita ali
         * é engolida em silêncio para requisições JSON; ela só sobrevive na
         * resposta HTML (`Accept: text/html`). Quem precisar que a mensagem
         * chegue ao cliente JSON vai ter que abrir uma exceção aqui antes
         * (por classe, não por status), ou usar um status diferente de 404.
         *
         * ── APP_DEBUG=true no ambiente de desenvolvimento ──
         *
         * A uniformização acima do corpo do 404 vale mesmo com
         * `APP_DEBUG=true`: em dev, o 404 de API também devolve
         * `{"message":"Not Found"}`, inclusive para rota inexistente (que sem
         * este `render()` devolveria, com debug ligado, a stack trace e o
         * nome da rota pedida). A decisão é deliberada: se um dia subir para
         * produção com `APP_DEBUG` ligado por engano, o vazamento de id/classe
         * de model e de rota que este bloco fecha não volta — o corpo do 404
         * não depende do valor de debug (ver
         * `tests/Feature/Autorizacao/CorpoDo404Test::test_o_corpo_nao_depende_do_app_debug`).
         * A informação de debug não desaparece: continua disponível na MESMA
         * URL para quem mandar `Accept: text/html` (a página de erro do
         * Symfony/Ignition, fora do `expectsJson()`), só não vaza mais para o
         * cliente JSON por padrão.
         */
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $status = match (true) {
                $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface => $e->getStatusCode(),
                $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException => 404,
                $e instanceof \Illuminate\Auth\Access\AuthorizationException && $e->hasStatus() => $e->status(),
                default => null,
            };

            return $status === 404
                ? response()->json(['message' => 'Not Found'], 404)
                : null;
        });
    })->create();
