<?php

namespace Tests\Feature\Listagem;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Toda listagem paginada responde no mesmo formato e aceita os mesmos
 * parâmetros: page, per_page (teto 100), sort (lista fechada), dir, busca.
 */
class ContratoDeListagemTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_formato_padrao_data_meta_sort(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $this->lead($tenant, $dono);

        $this->actingAs($dono)->getJson('/api/leads')->assertOk()->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'last_page', 'per_page', 'total', 'from', 'to'],
            'sort' => ['por', 'dir'],
        ])->assertJsonPath('meta.total', 1)->assertJsonPath('sort.por', 'created_at')->assertJsonPath('sort.dir', 'desc');
    }

    public function test_per_page_acima_do_teto_e_limitado_a_100(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);

        $this->actingAs($dono)->getJson('/api/leads?per_page=500')->assertOk()->assertJsonPath('meta.per_page', 100);
    }

    public function test_sort_fora_da_lista_e_dir_invalido_sao_422(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);

        $this->actingAs($dono)->getJson('/api/leads?sort=senha')->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->actingAs($dono)->getJson('/api/leads?sort=nome&dir=sideways')->assertUnprocessable()->assertJsonValidationErrors('dir');
    }

    public function test_tarefas_e_lixeira_seguem_o_mesmo_contrato(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);

        $this->actingAs($dono)->getJson('/api/tarefas')->assertOk()->assertJsonStructure(['data', 'meta' => ['total', 'per_page'], 'sort' => ['por', 'dir']]);
        $this->actingAs($dono)->getJson('/api/tarefas?sort=cor')->assertUnprocessable();
    }
}
