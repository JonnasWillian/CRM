<?php

namespace Tests\Feature\Exclusao;

use App\Models\Projeto;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class LixeiraDeProjetoTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private User $dono;
    private User $intruso;
    private User $gestor;
    private Usuario $lead;
    private Projeto $projeto;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->novoTenant();
        $this->dono = $this->agente($tenant);
        $this->intruso = $this->agente($tenant);
        $this->gestor = $this->agente($tenant, 'gestor');
        $this->lead = $this->lead($tenant, $this->dono);
        $this->projeto = $this->projeto($this->lead, ['preco' => 1500]);
    }

    /** O botão "excluir projeto" do ProjetoPanel recebia 405: a rota não existia. */
    public function test_excluir_projeto_responde_200_e_vai_para_a_lixeira(): void
    {
        $this->actingAs($this->dono)->deleteJson("/api/projeto/{$this->projeto->id}")->assertOk();

        $this->assertSoftDeleted('projetos', ['id' => $this->projeto->id]);
        $this->assertDatabaseHas('activity_log', ['lead_id' => $this->lead->id, 'event' => 'projeto_removido']);
        $this->actingAs($this->dono)->getJson("/api/projeto/{$this->projeto->id}")->assertNotFound();
    }

    public function test_projeto_na_lixeira_sai_da_listagem_e_do_valor_do_kanban(): void
    {
        $this->projeto->delete();

        $this->assertSame([], $this->actingAs($this->dono)->getJson('/api/projetos?'.http_build_query(['usuario_id' => $this->lead->id]))->json());

        $card = collect($this->actingAs($this->dono)->getJson('/api/kanban')->json('leads'))->firstWhere('id', $this->lead->id);
        $this->assertEquals(0, $card['valor_projetos'] ?? 0);
    }

    public function test_restaurar_projeto(): void
    {
        $this->projeto->delete();

        $this->actingAs($this->dono)->patchJson("/api/projeto/{$this->projeto->id}/restaurar")->assertOk();

        $this->assertNotSoftDeleted('projetos', ['id' => $this->projeto->id]);
        $this->assertDatabaseHas('activity_log', ['lead_id' => $this->lead->id, 'event' => 'projeto_restaurado']);
    }

    public function test_colega_nao_exclui_nem_restaura_projeto(): void
    {
        $this->actingAs($this->intruso)->deleteJson("/api/projeto/{$this->projeto->id}")->assertNotFound();

        $this->projeto->delete();
        $this->actingAs($this->intruso)->patchJson("/api/projeto/{$this->projeto->id}/restaurar")->assertNotFound();
    }

    /** Review Focus 2: nem o gestor alcança o projeto de um lead que está na lixeira. */
    public function test_projeto_de_lead_na_lixeira_da_404_ate_para_o_gestor(): void
    {
        $this->lead->delete();

        $this->actingAs($this->gestor)->getJson("/api/projeto/{$this->projeto->id}")->assertNotFound();
        $this->actingAs($this->gestor)->getJson("/api/projetoAnotacao/{$this->projeto->id}")->assertNotFound();
    }
}
