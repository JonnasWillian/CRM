<?php

namespace Tests\Feature\Reports;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\StatusProjeto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Faturando × em projeção. Contrato recorrente fatura enquanto está vigente
 * e não foi cancelado — o status do projeto descreve a entrega (Em
 * desenvolvimento, Pausado…), não a venda. Contrato recorrente sai de
 * "projetos em aberto" para não somar o total do contrato duas vezes.
 */
class FaturamentoEProjecaoTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private function cenario(): array
    {
        Carbon::setTestNow('2026-10-19 10:00:00');
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $ana = $this->agente($tenant);
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $aberto = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $lead = $this->lead($tenant, $ana, ['estagio_id' => $aberto->id, 'valor_estimado' => 10000, 'probabilidade' => 50]);
        $status = [
            'desenvolvimento' => StatusProjeto::factory()->create(['tenant_id' => $tenant->id, 'descricao' => 'Em desenvolvimento']),
            'concluido' => StatusProjeto::factory()->create(['tenant_id' => $tenant->id, 'is_won' => true]),
            'cancelado' => StatusProjeto::factory()->create(['tenant_id' => $tenant->id, 'is_lost' => true]),
        ];

        $novo = fn (array $a) => $this->actingAs($ana)->postJson("/api/leads/{$lead->id}/projetos", ['nome' => 'Projeto de teste', ...$a])->assertCreated()->json('data.id');

        // Vigente em "Em desenvolvimento": fatura. 15/08 → conta ago, set, out.
        $novo(['tipo' => 'recorrente', 'status_id' => $status['desenvolvimento']->id, 'valor_mensal' => 3000, 'duracao_meses' => 12, 'data_inicial' => '2026-08-15']);
        // Começa no mês que vem: projeção.
        $novo(['tipo' => 'recorrente', 'status_id' => $status['desenvolvimento']->id, 'valor_mensal' => 1000, 'duracao_meses' => 6, 'data_inicial' => '2026-11-10']);
        // Cancelado hoje: os meses até o cancelamento foram faturados (01/10 cai no período);
        // não entra no MRR, no acumulado dos contratos ativos nem na projeção.
        $novo(['tipo' => 'recorrente', 'status_id' => $status['cancelado']->id, 'valor_mensal' => 5000, 'duracao_meses' => 12, 'data_inicial' => '2026-01-01', 'perda' => ['motivo_perda_id' => \App\Models\MotivoPerda::factory()->create(['tenant_id' => $tenant->id])->id]]);
        // Único em negociação e único ganho agora.
        $novo(['status_id' => $status['desenvolvimento']->id, 'preco' => 8000]);
        $novo(['status_id' => $status['concluido']->id, 'preco' => 2500]);

        return compact('gestor', 'ana', 'lead');
    }

    public function test_metricas_separam_faturamento_de_projecao(): void
    {
        ['gestor' => $gestor] = $this->cenario();

        $m = $this->actingAs($gestor)->getJson('/api/metricas?periodo=30d')->assertOk()->json();

        $this->assertEquals(3000, $m['faturamento']['mrr']);
        $this->assertSame(1, $m['faturamento']['contratos_vigentes']);
        $this->assertEquals(3000 + 5000, $m['faturamento']['recorrente_no_periodo']); // 15/10 do vigente + 01/10 do cancelado
        $this->assertEquals(2500, $m['faturamento']['projetos_unicos_no_periodo']);
        $this->assertEquals(10500, $m['faturamento']['total_no_periodo']);
        $this->assertEquals(9000, $m['faturamento']['recorrente_acumulado']);

        $this->assertEquals(27000 + 6000, $m['projecao']['recorrente_a_realizar']); // 9 meses restantes + contrato a iniciar
        $this->assertSame(1, $m['projecao']['contratos_a_iniciar']);
        $this->assertEquals(8000, $m['projecao']['projetos_em_negociacao']);
        $this->assertSame(1, $m['projecao']['projetos_em_negociacao_qtd']);
        $this->assertEquals(10000, $m['projecao']['leads_em_negociacao']);
        $this->assertEquals(5000, $m['projecao']['leads_ponderado']);

        // O total do contrato recorrente não aparece mais como "projetos em aberto".
        $this->assertEquals(8000, $m['forecast']['valor_projetos_abertos']);
    }

    public function test_relatorio_mostra_faturamento_projecao_e_recorrencia_por_vendedor(): void
    {
        ['gestor' => $gestor, 'ana' => $ana] = $this->cenario();

        $r = $this->actingAs($gestor)->getJson('/api/relatorios/visao-geral?periodo=30d')->assertOk()->json();

        $this->assertEquals(3000, $r['metricas']['faturamento']['mrr']);
        $this->assertEquals(8000, $r['metricas']['projecao']['projetos_em_negociacao']);
        $linhaAna = collect($r['por_vendedor'])->firstWhere('user_id', $ana->id);
        $this->assertEquals(3000, $linhaAna['mrr']);
        $this->assertEquals(33000, $linhaAna['recorrente_a_realizar']);
    }
}
