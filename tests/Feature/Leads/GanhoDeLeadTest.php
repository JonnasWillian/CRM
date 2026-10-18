<?php

namespace Tests\Feature\Leads;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Statu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/** Marcar como ganho: valor fechado, fechado_em e probabilidade 100; o perfil conta só projetos abertos para o aviso. */
class GanhoDeLeadTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_ganho_grava_valor_fechado_data_e_probabilidade_e_entra_nas_metricas(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $aberto = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 1]);
        $ganho = Estagio::factory()->ganho()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 2]);
        $lead = $this->lead($tenant, $dono, ['estagio_id' => $aberto->id, 'valor_estimado' => 10000, 'probabilidade' => 40]);

        $this->actingAs($dono)->patchJson("/api/leads/{$lead->id}/estagio", ['estagio_id' => $ganho->id, 'valor_fechado' => 12000])
            ->assertOk()->assertJsonPath('data.probabilidade', 100);

        $lead->refresh();
        $this->assertSame('12000.00', $lead->valor_fechado);
        $this->assertNotNull($lead->fechado_em);

        $this->actingAs($dono)->getJson('/api/metricas?periodo=7d')->assertOk()
            ->assertJsonPath('fluxo.ganhos.total', 1)
            ->assertJsonPath('fluxo.ganhos.valor', 12000);
    }

    public function test_perfil_traz_a_contagem_de_projetos_abertos(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);
        $this->projeto($lead);
        $this->projeto($lead, ['status_id' => Statu::factory()->create(['tenant_id' => $tenant->id, 'is_won' => true])->id]);

        $this->actingAs($dono)->getJson("/api/leads/{$lead->id}")->assertOk()
            ->assertJsonPath('projetos_count', 2)
            ->assertJsonPath('projetos_abertos_count', 1);
    }
}
