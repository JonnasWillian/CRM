<?php

namespace Tests\Feature\Reports;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\MotivoPerda;
use App\Models\Statu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * As métricas novas: fluxo (ganhos/perdidos/taxa por fechado_em e perdas no
 * período) e forecast (leads abertos, ponderado por probabilidade, por mês de
 * previsão). Nenhuma probabilidade é assumida: lead com valor e sem
 * probabilidade é contado à parte.
 */
class ForecastTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_fluxo_e_forecast(): void
    {
        Carbon::setTestNow('2026-10-17 12:00:00');
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $aberto = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $ganho = Estagio::factory()->ganho()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $perdido = Estagio::factory()->perdido()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $motivo = MotivoPerda::factory()->create(['tenant_id' => $tenant->id]);

        // Abertos: dois com previsão nos próximos meses, um sem probabilidade, um com previsão vencida.
        $this->lead($tenant, $dono, ['estagio_id' => $aberto->id, 'valor_estimado' => 10000, 'probabilidade' => 50, 'previsao_fechamento' => '2026-10-30']);
        $this->lead($tenant, $dono, ['estagio_id' => $aberto->id, 'valor_estimado' => 4000, 'probabilidade' => 25, 'previsao_fechamento' => '2026-11-10']);
        $this->lead($tenant, $dono, ['estagio_id' => $aberto->id, 'valor_estimado' => 3000, 'probabilidade' => null, 'previsao_fechamento' => '2026-11-20']);
        $this->lead($tenant, $dono, ['estagio_id' => $aberto->id, 'valor_estimado' => 1000, 'probabilidade' => 80, 'previsao_fechamento' => '2026-10-01']);

        // Ganho no período (valor fechado), ganho fora do período, perdido no período.
        $g1 = $this->lead($tenant, $dono, ['estagio_id' => $aberto->id, 'valor_estimado' => 5000]);
        $this->actingAs($dono)->patchJson("/api/usuarios/{$g1->id}/estagio", ['estagio_id' => $ganho->id, 'valor_fechado' => 5500])->assertOk();
        $g2 = $this->lead($tenant, $dono, ['estagio_id' => $aberto->id, 'valor_estimado' => 7000]);
        $this->actingAs($dono)->patchJson("/api/usuarios/{$g2->id}/estagio", ['estagio_id' => $ganho->id])->assertOk();
        \App\Models\Usuario::whereKey($g2->id)->update(['fechado_em' => '2026-08-01 10:00:00']);
        $p1 = $this->lead($tenant, $dono, ['estagio_id' => $aberto->id, 'valor_estimado' => 2000]);
        $this->actingAs($dono)->patchJson("/api/usuarios/{$p1->id}/estagio", ['estagio_id' => $perdido->id, 'perda' => ['motivo_perda_id' => $motivo->id]])->assertOk();

        $statusAberto = Statu::factory()->create(['tenant_id' => $tenant->id]);
        $this->projeto($g1, ['preco' => 900, 'status_id' => $statusAberto->id]);

        $r = $this->actingAs($dono)->getJson('/api/metricas?periodo=30d')->assertOk()->json();

        $this->assertSame(1, $r['fluxo']['ganhos']['total']);
        $this->assertEquals(5500, $r['fluxo']['ganhos']['valor']);
        $this->assertSame(1, $r['fluxo']['perdidos']['total']);
        $this->assertEquals(50.0, $r['fluxo']['taxa_ganho']);
        $this->assertEquals(5500, $r['fluxo']['ticket_medio']);

        $this->assertEquals(18000, $r['forecast']['valor_estimado_aberto']);
        $this->assertEquals(10000 * .5 + 4000 * .25 + 1000 * .8, $r['forecast']['valor_ponderado']);
        $this->assertSame(1, $r['forecast']['sem_probabilidade']);
        $this->assertSame(3, $r['forecast']['leads_com_previsao']);
        $this->assertSame(1, $r['forecast']['previsao_vencida']);
        $this->assertEquals(900, $r['forecast']['valor_projetos_abertos']);
        $this->assertSame(['2026-10', '2026-11', '2026-12'], array_column($r['forecast']['por_mes'], 'mes'));
        $this->assertEquals(10000, $r['forecast']['por_mes'][0]['valor_estimado']);
        $this->assertEquals(7000, $r['forecast']['por_mes'][1]['valor_estimado']);
        $this->assertSame(2, $r['forecast']['por_mes'][1]['total']);

        $this->assertSame(4, $r['estado']['leads_ativos']);
        $this->assertSame(['inicio' => '2026-09-17', 'fim' => '2026-10-17', 'preset' => '30d', 'rotulo' => 'Últimos 30 dias'], $r['periodo']);
        Carbon::setTestNow();
    }

    public function test_periodo_customizado_e_filtro_por_vendedor_para_quem_ve_tudo(): void
    {
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $vendedor = $this->agente($tenant);
        $this->lead($tenant, $vendedor);
        $this->lead($tenant, $gestor);

        $this->actingAs($gestor)->getJson('/api/metricas?user_id='.$vendedor->id)->assertOk()->assertJsonPath('total_leads', 1);
        $this->actingAs($gestor)->getJson('/api/metricas')->assertOk()->assertJsonPath('total_leads', 2);
        $this->actingAs($vendedor)->getJson('/api/metricas?user_id='.$gestor->id)->assertOk()->assertJsonPath('total_leads', 1);

        $this->actingAs($gestor)->getJson('/api/metricas?periodo=custom&de=2026-01-01&ate=2026-01-31')->assertOk()
            ->assertJsonPath('periodo.preset', 'custom')->assertJsonPath('leads_30_dias', 0);
        $this->actingAs($gestor)->getJson('/api/metricas?periodo=custom&de=2026-02-01&ate=2026-01-31')->assertUnprocessable();
        $this->actingAs($gestor)->getJson('/api/metricas?periodo=ontem')->assertUnprocessable();
    }
}
