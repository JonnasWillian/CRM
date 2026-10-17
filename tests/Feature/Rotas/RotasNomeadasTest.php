<?php

namespace Tests\Feature\Rotas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Toda rota de API tem nome com prefixo `api.`.
 *
 * O front chama a API com `route('api.…')` (Ziggy expõe todas as rotas
 * nomeadas via @routes). Uma rota sem nome obriga a URL cravada no .vue,
 * que foi o que deixou ~60 chamadas axios espalhadas sem nenhum ponto de
 * verificação quando a URL muda.
 */
class RotasNomeadasTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    public function test_toda_rota_de_api_tem_nome_com_prefixo_api(): void
    {
        $semNome = [];

        foreach (Route::getRoutes() as $rota) {
            if (! str_starts_with($rota->uri(), 'api/')) {
                continue;
            }

            $nome = $rota->getName();

            if ($nome === null || ! str_starts_with($nome, 'api.')) {
                $semNome[] = implode('|', $rota->methods()).' '.$rota->uri().' => '.($nome ?? 'sem nome');
            }
        }

        $this->assertSame([], $semNome, "Rotas de API sem nome `api.*`:\n".implode("\n", $semNome));
    }

    public function test_nomes_de_rota_de_api_sao_unicos(): void
    {
        $nomes = [];

        foreach (Route::getRoutes() as $rota) {
            if (str_starts_with($rota->uri(), 'api/') && $rota->getName() !== null) {
                $nomes[] = $rota->getName();
            }
        }

        $duplicados = array_keys(array_filter(array_count_values($nomes), fn ($n) => $n > 1));

        $this->assertSame([], $duplicados, 'Nomes de rota duplicados: '.implode(', ', $duplicados));
    }

    public function test_rota_api_user_foi_removida(): void
    {
        $tenant = $this->novoTenant();
        $agente = $this->agente($tenant);

        $this->actingAs($agente)->getJson('/api/user')->assertNotFound();
    }
}
