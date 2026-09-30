<?php

namespace Tests\Feature\Exclusao;

use App\Models\Anotacao;
use App\Models\MotivoPerda;
use App\Models\Perda;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class LixeiraDeLeadTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;
    private User $dono;
    private User $intruso;
    private User $gestor;
    private Usuario $lead;
    private Anotacao $anotacao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->novoTenant();
        $this->dono = $this->agente($this->tenant);
        $this->intruso = $this->agente($this->tenant);
        $this->gestor = $this->agente($this->tenant, 'gestor');
        $this->lead = $this->lead($this->tenant, $this->dono, ['email' => 'cliente@exemplo.com']);
        $this->anotacao = Anotacao::create(['descricao' => 'Primeiro contato', 'usuario_id' => $this->lead->id]);
    }

    public function test_excluir_manda_para_a_lixeira_e_responde_200(): void
    {
        $this->actingAs($this->dono)->deleteJson("/api/usuarios/{$this->lead->id}")->assertOk();

        $this->assertSoftDeleted('usuarios', ['id' => $this->lead->id]);
        $this->assertDatabaseHas('anotacaos', ['id' => $this->anotacao->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('activity_log', ['lead_id' => $this->lead->id, 'event' => 'lead_removido']);
    }

    public function test_lead_na_lixeira_some_das_listagens_e_das_metricas(): void
    {
        $antes = $this->actingAs($this->dono)->getJson('/api/metricas')->json('total_leads');

        $this->actingAs($this->dono)->deleteJson("/api/usuarios/{$this->lead->id}")->assertOk();

        $this->assertSame($antes - 1, $this->actingAs($this->dono)->getJson('/api/metricas')->json('total_leads'));
        $this->assertNotContains($this->lead->id, collect($this->actingAs($this->dono)->getJson('/api/leads')->json('data'))->pluck('id'));
        $this->assertNotContains($this->lead->id, collect($this->actingAs($this->dono)->getJson('/api/kanban')->json('leads'))->pluck('id'));
    }

    /** Review Focus 2 */
    public function test_link_salvo_para_lead_na_lixeira_da_404_limpo(): void
    {
        $this->lead->delete();

        $this->actingAs($this->gestor)->get("/leads/{$this->lead->id}")->assertNotFound();
        $this->actingAs($this->gestor)->getJson("/api/usuarioPerfil/{$this->lead->id}")->assertNotFound();
        $this->actingAs($this->gestor)->putJson("/api/anotacao/{$this->anotacao->id}", ['descricao' => 'x'])->assertNotFound();
    }

    public function test_restaurar_traz_o_lead_de_volta_com_o_historico(): void
    {
        $this->lead->delete();

        $this->actingAs($this->dono)->patchJson("/api/usuarios/{$this->lead->id}/restaurar")->assertOk();

        $this->assertNotSoftDeleted('usuarios', ['id' => $this->lead->id]);
        $this->actingAs($this->dono)->getJson("/api/anotacao/{$this->lead->id}")
            ->assertOk()->assertJsonPath('0.id', $this->anotacao->id);
        $this->assertDatabaseHas('activity_log', ['lead_id' => $this->lead->id, 'event' => 'lead_restaurado']);
    }

    public function test_colega_nao_exclui_nem_restaura(): void
    {
        $this->actingAs($this->intruso)->deleteJson("/api/usuarios/{$this->lead->id}")->assertNotFound();
        $this->assertNotSoftDeleted('usuarios', ['id' => $this->lead->id]);

        $this->lead->delete();
        $this->actingAs($this->intruso)->patchJson("/api/usuarios/{$this->lead->id}/restaurar")->assertNotFound();
        $this->assertSoftDeleted('usuarios', ['id' => $this->lead->id]);
    }

    public function test_gestor_restaura_lead_da_equipe(): void
    {
        $this->lead->delete();

        $this->actingAs($this->gestor)->patchJson("/api/usuarios/{$this->lead->id}/restaurar")->assertOk();
    }

    public function test_email_de_lead_na_lixeira_explica_que_da_para_restaurar(): void
    {
        $this->lead->delete();

        $this->actingAs($this->dono)->postJson('/api/usuarios', [
            'nome' => 'Cliente de novo',
            'email' => 'cliente@exemplo.com',
            'telefone' => '11999998888',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'lead excluído']);
    }

    public function test_email_de_lead_ativo_continua_com_a_mensagem_de_sempre(): void
    {
        $this->actingAs($this->dono)->postJson('/api/usuarios', [
            'nome' => 'Cliente repetido',
            'email' => 'cliente@exemplo.com',
            'telefone' => '11999998888',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'já está sendo utilizado']);
    }

    public function test_editar_o_proprio_lead_sem_trocar_email_continua_valendo(): void
    {
        $this->actingAs($this->dono)->putJson("/api/usuarios/{$this->lead->id}", [
            'nome' => 'Cliente renomeado',
            'email' => 'cliente@exemplo.com',
            'telefone' => '11999998888',
        ])->assertOk();
    }

    /** Perda é fato histórico: continua no relatório depois que o lead vai para a lixeira. */
    public function test_perdas_do_lead_na_lixeira_continuam_no_relatorio(): void
    {
        $motivo = MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->lead->perdas()->save(new Perda(['motivo_perda_id' => $motivo->id, 'user_id' => $this->dono->id]));

        $antes = $this->actingAs($this->gestor)->getJson('/api/relatorios/perdas')->json('total');
        $this->lead->delete();

        $this->assertSame($antes, $this->actingAs($this->gestor)->getJson('/api/relatorios/perdas')->json('total'));
    }
}
