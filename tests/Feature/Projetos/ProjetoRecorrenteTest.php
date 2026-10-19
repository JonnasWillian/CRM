<?php

namespace Tests\Feature\Projetos;

use App\Models\Activity;
use App\Models\Projeto;
use App\Models\StatusProjeto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/** Projeto recorrente: valor mensal × prazo, renovações e aditivos, contagem até o mês atual. */
class ProjetoRecorrenteTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private function cenario(): array
    {
        Carbon::setTestNow('2026-10-19 10:00:00');
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);
        $aberto = StatusProjeto::factory()->create(['tenant_id' => $tenant->id]);
        $ganho = StatusProjeto::factory()->create(['tenant_id' => $tenant->id, 'is_won' => true]);

        return compact('tenant', 'dono', 'lead', 'aberto', 'ganho');
    }

    private function criar(array $c, array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($c['dono'])->postJson("/api/leads/{$c['lead']->id}/projetos", [
            'nome' => 'Gestão de tráfego', 'status_id' => $c['ganho']->id, 'tipo' => 'recorrente',
            'valor_mensal' => 1500, 'duracao_meses' => 12, 'data_inicial' => '2026-08-15', ...$extra,
        ]);
    }

    public function test_cria_recorrente_e_devolve_a_contagem(): void
    {
        $c = $this->cenario();

        $dados = $this->criar($c)->assertCreated()->json('data');

        $this->assertSame('recorrente', $dados['tipo']);
        $this->assertSame('vigente', $dados['recorrencia']['situacao']);
        $this->assertSame(3, $dados['recorrencia']['meses_decorridos']);
        $this->assertEquals(4500, $dados['recorrencia']['valor_realizado']);
        $this->assertEquals(13500, $dados['recorrencia']['valor_a_realizar']);
        $this->assertEquals(1500, $dados['recorrencia']['valor_mes_atual']);
        $this->assertSame('2027-08-14', $dados['recorrencia']['fim']);

        // O valor do projeto é o total do contrato (as somas existentes continuam valendo).
        $projeto = Projeto::find($dados['id']);
        $this->assertEquals(18000, $projeto->preco);
        $this->assertSame('2027-08-14', $projeto->data_final->toDateString());
    }

    public function test_recorrente_exige_valor_mensal_prazo_e_inicio(): void
    {
        $c = $this->cenario();

        $this->criar($c, ['valor_mensal' => null, 'duracao_meses' => null, 'data_inicial' => null])
            ->assertUnprocessable()->assertJsonValidationErrors(['valor_mensal', 'duracao_meses', 'data_inicial']);
        $this->criar($c, ['duracao_meses' => 0])->assertUnprocessable()->assertJsonValidationErrors('duracao_meses');
    }

    public function test_projeto_unico_nao_tem_recorrencia(): void
    {
        $c = $this->cenario();
        $dados = $this->actingAs($c['dono'])->postJson("/api/leads/{$c['lead']->id}/projetos", ['nome' => 'Site institucional', 'status_id' => $c['aberto']->id, 'preco' => 5000])
            ->assertCreated()->json('data');

        $this->assertSame('unico', $dados['tipo']);
        $this->assertNull($dados['recorrencia']);
        $this->actingAs($c['dono'])->postJson("/api/projetos/{$dados['id']}/aditivos", ['tipo' => 'renovacao', 'meses' => 6])
            ->assertUnprocessable()->assertJsonPath('message', 'Só projetos recorrentes têm renovação e aditivos.');
    }

    public function test_renovacao_e_aditivo_estendem_o_prazo_e_mudam_o_valor(): void
    {
        $c = $this->cenario();
        $id = $this->criar($c, ['duracao_meses' => 3])->json('data.id');

        $renovado = $this->actingAs($c['dono'])->postJson("/api/projetos/{$id}/aditivos", ['tipo' => 'renovacao', 'meses' => 6, 'valor_mensal' => 1800, 'observacao' => 'Reajuste anual'])
            ->assertCreated()->json('data');
        $this->assertSame(9, $renovado['recorrencia']['meses_total']);
        $this->assertEquals(4500 + 10800, $renovado['recorrencia']['valor_total']);
        $this->assertSame('2027-05-14', $renovado['recorrencia']['fim']);
        $this->assertCount(1, $renovado['aditivos']);

        $aditivado = $this->actingAs($c['dono'])->postJson("/api/projetos/{$id}/aditivos", ['tipo' => 'aditivo', 'meses' => 2])->assertCreated()->json('data');
        $this->assertSame(11, $aditivado['recorrencia']['meses_total']);
        $this->assertEquals(1800, $aditivado['recorrencia']['periodos'][2]['valor_mensal']);
        $this->assertEquals(4500 + 10800 + 3600, Projeto::find($id)->preco);

        $evento = Activity::where('event', 'projeto_aditivo')->latest('id')->first();
        $this->assertNotNull($evento);
        $this->assertSame($c['lead']->id, $evento->lead_id);
        $atividades = $this->actingAs($c['dono'])->getJson("/api/leads/{$c['lead']->id}/atividades")->json('data');
        $this->assertContains('projeto_aditivo', array_column($atividades, 'tipo'));

        // Só o último sai, para a sequência de períodos nunca ter buraco.
        $primeiro = $renovado['aditivos'][0]['id'];
        $ultimo = $aditivado['aditivos'][1]['id'];
        $this->actingAs($c['dono'])->deleteJson("/api/projeto-aditivos/{$primeiro}")->assertUnprocessable()->assertJsonMissingPath('errors');
        $this->actingAs($c['dono'])->deleteJson("/api/projeto-aditivos/{$ultimo}")->assertOk();
        $this->assertEquals(4500 + 10800, Projeto::find($id)->preco);
    }

    public function test_colega_nao_mexe_no_contrato(): void
    {
        $c = $this->cenario();
        $id = $this->criar($c)->json('data.id');
        $colega = $this->agente($c['tenant']);

        $this->actingAs($colega)->postJson("/api/projetos/{$id}/aditivos", ['tipo' => 'renovacao', 'meses' => 6])->assertNotFound();
    }

    public function test_metricas_somam_a_receita_recorrente_dos_contratos_ganhos_vigentes(): void
    {
        $c = $this->cenario();
        $this->criar($c); // ganho, vigente: 1.500/mês
        $this->criar($c, ['valor_mensal' => 900, 'status_id' => $c['aberto']->id]); // proposta: não conta
        $this->criar($c, ['valor_mensal' => 700, 'data_inicial' => '2025-01-10', 'duracao_meses' => 6]); // encerrado

        $r = $this->actingAs($c['dono'])->getJson('/api/metricas')->assertOk()->json('recorrencia');

        $this->assertEquals(1500, $r['mrr']);
        $this->assertSame(1, $r['contratos_vigentes']);
        $this->assertEquals(13500, $r['a_realizar']);
        $this->assertSame(0, $r['vencendo_30d']);
    }
}
