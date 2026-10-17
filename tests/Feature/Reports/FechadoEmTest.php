<?php

namespace Tests\Feature\Reports;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\MotivoPerda;
use App\Models\Statu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * "Fechado no período" lê uma coluna de evento (`fechado_em`), preenchida
 * quando o lead entra em ganho/perdido ou o projeto entra em status ganho,
 * e zerada ao reabrir — nunca `updated_at`, que muda a cada edição.
 *
 * Também aqui: o estágio pode sugerir uma probabilidade, que só preenche o
 * lead quando o campo está vazio.
 */
class FechadoEmTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private function cenario(): array
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);
        $funil = Funil::findOrFail($lead->funil_id);
        $aberto = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'probabilidade_padrao' => 40]);
        $ganho = Estagio::factory()->ganho()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $perdido = Estagio::factory()->perdido()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $lead->forceFill(['estagio_id' => $aberto->id])->saveQuietly();

        return compact('tenant', 'dono', 'lead', 'funil', 'aberto', 'ganho', 'perdido');
    }

    public function test_lead_ganha_fechado_em_ao_entrar_em_ganho_e_perde_ao_reabrir(): void
    {
        ['dono' => $dono, 'lead' => $lead, 'aberto' => $aberto, 'ganho' => $ganho] = $this->cenario();

        $this->assertNull($lead->fresh()->fechado_em);

        $this->travelTo(now()->addDay());
        $this->actingAs($dono)->patchJson("/api/usuarios/{$lead->id}/estagio", ['estagio_id' => $ganho->id])->assertOk();
        $this->assertTrue($lead->fresh()->fechado_em->isSameSecond(now()));

        $this->actingAs($dono)->patchJson("/api/usuarios/{$lead->id}/estagio", ['estagio_id' => $aberto->id])->assertOk();
        $this->assertNull($lead->fresh()->fechado_em);
    }

    public function test_lead_perdido_tambem_recebe_fechado_em(): void
    {
        ['tenant' => $tenant, 'dono' => $dono, 'lead' => $lead, 'perdido' => $perdido] = $this->cenario();
        $motivo = MotivoPerda::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($dono)->patchJson("/api/usuarios/{$lead->id}/estagio", [
            'estagio_id' => $perdido->id,
            'perda' => ['motivo_perda_id' => $motivo->id],
        ])->assertOk();

        $this->assertNotNull($lead->fresh()->fechado_em);
    }

    public function test_projeto_ganha_fechado_em_com_status_ganho_e_perde_ao_voltar(): void
    {
        ['tenant' => $tenant, 'lead' => $lead] = $this->cenario();
        $projeto = $this->projeto($lead);
        $aberto = $projeto->status_id;
        $ganho = Statu::factory()->create(['tenant_id' => $tenant->id, 'is_won' => true]);

        $this->assertNull($projeto->fresh()->fechado_em);

        $projeto->update(['status_id' => $ganho->id]);
        $this->assertNotNull($projeto->fresh()->fechado_em);

        $projeto->update(['status_id' => $aberto]);
        $this->assertNull($projeto->fresh()->fechado_em);
    }

    public function test_probabilidade_padrao_do_estagio_preenche_so_quando_vazia(): void
    {
        ['tenant' => $tenant, 'dono' => $dono, 'funil' => $funil, 'aberto' => $aberto] = $this->cenario();

        $this->actingAs($dono)->postJson('/api/usuarios', [
            'nome' => 'Lead Sem Probabilidade', 'email' => 'sem@acme.test', 'funil_id' => $funil->id, 'estagio_id' => $aberto->id,
        ])->assertCreated();
        $this->assertDatabaseHas('usuarios', ['email' => 'sem@acme.test', 'probabilidade' => 40]);

        $this->actingAs($dono)->postJson('/api/usuarios', [
            'nome' => 'Lead Com Probabilidade', 'email' => 'com@acme.test', 'funil_id' => $funil->id, 'estagio_id' => $aberto->id, 'probabilidade' => 70,
        ])->assertCreated();
        $this->assertDatabaseHas('usuarios', ['email' => 'com@acme.test', 'probabilidade' => 70]);

        $outro = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'probabilidade_padrao' => 55]);
        $comValor = \App\Models\Usuario::where('email', 'com@acme.test')->firstOrFail();
        $this->actingAs($dono)->patchJson("/api/usuarios/{$comValor->id}/estagio", ['estagio_id' => $outro->id])->assertOk();
        $this->assertSame(70, $comValor->fresh()->probabilidade, 'valor explícito não é sobrescrito');

        $semValor = \App\Models\Usuario::where('email', 'sem@acme.test')->firstOrFail();
        $semValor->forceFill(['probabilidade' => null])->saveQuietly();
        $this->actingAs($dono)->patchJson("/api/usuarios/{$semValor->id}/estagio", ['estagio_id' => $outro->id])->assertOk();
        $this->assertSame(55, $semValor->fresh()->probabilidade);
    }
}
