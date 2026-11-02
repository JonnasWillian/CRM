<?php

namespace Tests\Feature\Api;

use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\TestCase;

/**
 * As telas chamam a API com a sessão do navegador (statefulApi do Sanctum).
 * O Sanctum só abre a sessão para origens da lista `sanctum.stateful`; fora
 * dela, a chamada chega sem usuário e volta 401 "Unauthenticated", com a
 * página aberta e logada.
 *
 * Em desenvolvimento, `php artisan serve` sobe na 8001, 8002… quando a 8000
 * está ocupada por outro projeto. Qualquer porta local precisa valer.
 */
class SessaoDoFrontNaApiTest extends TestCase
{
    private function vemDoFront(string $referer): bool
    {
        return EnsureFrontendRequestsAreStateful::fromFrontend(Request::create('/api/leads', 'GET', server: ['HTTP_REFERER' => $referer]));
    }

    public function test_o_front_em_qualquer_porta_local_usa_a_sessao_na_api(): void
    {
        foreach (['http://127.0.0.1:8000/leads', 'http://127.0.0.1:8001/leads/38?aba=projetos', 'http://localhost:8002/dashboard', 'http://localhost/leads', 'http://127.0.0.1/kanban'] as $origem) {
            $this->assertTrue($this->vemDoFront($origem), "deveria usar a sessão vindo de {$origem}");
        }
    }

    public function test_o_endereco_do_app_url_usa_a_sessao(): void
    {
        $this->assertTrue($this->vemDoFront(rtrim(config('app.url'), '/').'/leads'));
    }

    public function test_outros_sites_nao_usam_a_sessao(): void
    {
        foreach (['https://outro-site.com/pagina', 'http://127.0.0.1.outro-site.com/x', 'http://localhost.evil.test/x'] as $origem) {
            $this->assertFalse($this->vemDoFront($origem), "não deveria usar a sessão vindo de {$origem}");
        }
    }
}
