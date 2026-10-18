<?php

namespace Tests\Feature\Leads;

use App\Models\Activity;
use App\Models\Tarefa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Transferir lead = trocar o responsável. Só quem tem leads.view-all; o
 * destino é um membro ativo da mesma empresa. Fica no histórico do lead.
 */
class TransferenciaDeLeadsTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_gestor_transfere_um_lead_e_o_historico_registra(): void
    {
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $ana = $this->agente($tenant);
        $bia = $this->agente($tenant);
        $lead = $this->lead($tenant, $ana);
        Tarefa::create(['usuario_id' => $lead->id, 'titulo' => 'Ligar', 'data_limite' => today()]);

        $this->actingAs($gestor)->patchJson("/api/leads/{$lead->id}/responsavel", ['user_id' => $bia->id])
            ->assertOk()->assertJsonPath('data.responsavel.id', $bia->id);

        $this->assertSame($bia->id, $lead->fresh()->user_id);
        $evento = Activity::where('lead_id', $lead->id)->where('event', 'responsavel_alterado')->first();
        $this->assertNotNull($evento);
        $this->assertSame($ana->name, $evento->properties['responsavel_anterior']);
        $this->assertSame($bia->name, $evento->properties['responsavel_novo']);

        // A tarefa segue o lead: Bia passa a vê-la.
        $this->actingAs($bia)->getJson('/api/tarefas/resumo')->assertJsonPath('hoje.total', 1);

        $atividades = $this->actingAs($gestor)->getJson("/api/leads/{$lead->id}/atividades")->json('data');
        $linha = collect($atividades)->firstWhere('tipo', 'responsavel_alterado');
        $this->assertSame($ana->name, $linha['responsavel_anterior']);
        $this->assertSame($bia->name, $linha['responsavel_novo']);
    }

    public function test_vendedor_nao_transfere_e_destino_precisa_ser_ativo_e_da_empresa(): void
    {
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $ana = $this->agente($tenant);
        $inativo = $this->agente($tenant);
        $inativo->forceFill(['ativo' => false, 'desativado_em' => now()])->save();
        $lead = $this->lead($tenant, $ana);

        $this->actingAs($ana)->patchJson("/api/leads/{$lead->id}/responsavel", ['user_id' => $gestor->id])->assertForbidden();
        $this->actingAs($gestor)->patchJson("/api/leads/{$lead->id}/responsavel", ['user_id' => $inativo->id])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');

        $outro = $this->novoTenant();
        $alheio = $this->agente($outro);
        $this->ativar($tenant);
        $this->actingAs($gestor)->patchJson("/api/leads/{$lead->id}/responsavel", ['user_id' => $alheio->id])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');

        $this->assertSame($ana->id, $lead->fresh()->user_id);
    }

    public function test_transferir_em_massa_por_ids_e_por_filtro(): void
    {
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $ana = $this->agente($tenant);
        $bia = $this->agente($tenant);
        $a = $this->lead($tenant, $ana);
        $b = $this->lead($tenant, $ana);
        $jaDaBia = $this->lead($tenant, $bia);

        $this->actingAs($gestor)->postJson('/api/leads/transferir', ['ids' => [$a->id, $jaDaBia->id], 'user_id' => $bia->id])
            ->assertOk()->assertJsonPath('afetados', 1)->assertJsonPath('ignorados', 1);
        $this->assertSame($bia->id, $a->fresh()->user_id);

        $this->actingAs($gestor)->postJson('/api/leads/transferir', ['todos' => true, 'filtros' => ['user_id' => $ana->id], 'user_id' => $bia->id])
            ->assertOk()->assertJsonPath('afetados', 1);
        $this->assertSame($bia->id, $b->fresh()->user_id);

        $this->actingAs($ana)->postJson('/api/leads/transferir', ['ids' => [$a->id], 'user_id' => $ana->id])->assertForbidden();
    }
}
