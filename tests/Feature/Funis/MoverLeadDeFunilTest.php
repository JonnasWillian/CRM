<?php

namespace Tests\Feature\Funis;

use App\Models\Activity;
use App\Models\Estagio;
use App\Models\EstagioHistorico;
use App\Models\Funil;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O lead vive em um funil por vez, e a troca é sempre explícita.
 *
 * O estado que estes testes existem para impedir é um só: lead cujo `funil_id`
 * diz "Pós-venda" e cujo `estagio_id` é uma coluna de "Vendas". Nenhuma tela
 * sabe desenhar isso — o Kanban do funil de destino não acharia a coluna, e o
 * card sumiria do quadro sem nenhum erro aparecer.
 */
class MoverLeadDeFunilTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $staff;
    private Funil $vendas;
    private Funil $posVenda;
    private Estagio $estagioVendas;
    private Estagio $estagioPosVenda;
    private Usuario $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
        app(CurrentTenant::class)->set($this->tenant);

        $this->vendas = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id, 'nome' => 'Vendas']);
        $this->posVenda = Funil::factory()->create(['tenant_id' => $this->tenant->id, 'nome' => 'Pós-venda']);

        $this->estagioVendas = Estagio::factory()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $this->vendas->id]);
        $this->estagioPosVenda = Estagio::factory()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $this->posVenda->id]);

        $this->lead = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->staff->id,
            'funil_id' => $this->vendas->id,
            'estagio_id' => $this->estagioVendas->id,
        ]);
    }

    public function test_mover_troca_funil_e_estagio_juntos(): void
    {
        $this->actingAs($this->staff)
            ->patchJson("/api/usuarios/{$this->lead->id}/funil", [
                'funil_id' => $this->posVenda->id,
                'estagio_id' => $this->estagioPosVenda->id,
            ])
            ->assertOk();

        $this->lead->refresh();

        $this->assertSame($this->posVenda->id, $this->lead->funil_id);
        $this->assertSame($this->estagioPosVenda->id, $this->lead->estagio_id);
    }

    public function test_mover_para_estagio_de_outro_funil_e_recusado(): void
    {
        $this->actingAs($this->staff)
            ->patchJson("/api/usuarios/{$this->lead->id}/funil", [
                'funil_id' => $this->posVenda->id,
                // Estágio de Vendas, funil de destino Pós-venda: a combinação
                // que deixaria o lead invisível no quadro.
                'estagio_id' => $this->estagioVendas->id,
            ])
            ->assertStatus(422);

        $this->lead->refresh();
        $this->assertSame($this->vendas->id, $this->lead->funil_id);
    }

    public function test_mover_registra_historico_com_os_dois_funis(): void
    {
        $this->actingAs($this->staff)
            ->patchJson("/api/usuarios/{$this->lead->id}/funil", [
                'funil_id' => $this->posVenda->id,
                'estagio_id' => $this->estagioPosVenda->id,
            ])
            ->assertOk();

        $historico = EstagioHistorico::where('usuario_id', $this->lead->id)->latest('id')->first();

        $this->assertNotNull($historico);
        $this->assertSame($this->vendas->id, $historico->funil_anterior_id);
        $this->assertSame($this->posVenda->id, $historico->funil_novo_id);
        $this->assertSame($this->estagioVendas->id, $historico->estagio_anterior_id);
        $this->assertSame($this->estagioPosVenda->id, $historico->estagio_novo_id);
    }

    public function test_mover_registra_evento_funil_alterado_e_nao_status_alterado(): void
    {
        $this->actingAs($this->staff)
            ->patchJson("/api/usuarios/{$this->lead->id}/funil", [
                'funil_id' => $this->posVenda->id,
                'estagio_id' => $this->estagioPosVenda->id,
            ])
            ->assertOk();

        $this->assertTrue(Activity::where('lead_id', $this->lead->id)->where('event', 'funil_alterado')->exists());
        $this->assertFalse(Activity::where('lead_id', $this->lead->id)->where('event', 'status_alterado')->exists());
    }

    /**
     * Arrastar um card no Kanban move o lead dentro do quadro. O quadro é um
     * funil, então um estagio_id de fora dele tem de ser recusado — senão o
     * drag-and-drop vira um caminho silencioso para o estado inconsistente.
     */
    public function test_arrastar_card_nao_atravessa_funil(): void
    {
        $this->actingAs($this->staff)
            ->patchJson("/api/usuarios/{$this->lead->id}/estagio", [
                'estagio_id' => $this->estagioPosVenda->id,
            ])
            ->assertStatus(422);

        $this->assertSame($this->estagioVendas->id, $this->lead->fresh()->estagio_id);
    }

    public function test_funil_de_outro_tenant_nao_e_destino_valido(): void
    {
        $outroTenant = Tenant::factory()->create();
        $funilAlheio = Funil::factory()->create(['tenant_id' => $outroTenant->id]);
        $estagioAlheio = Estagio::factory()->create(['tenant_id' => $outroTenant->id, 'funil_id' => $funilAlheio->id]);

        $this->actingAs($this->staff)
            ->patchJson("/api/usuarios/{$this->lead->id}/funil", [
                'funil_id' => $funilAlheio->id,
                'estagio_id' => $estagioAlheio->id,
            ])
            ->assertStatus(422);

        $this->assertSame($this->vendas->id, $this->lead->fresh()->funil_id);
    }

    /**
     * O lead criado sem funil explícito cai no funil padrão do tenant, no
     * primeiro estágio aberto. É o que substitui o `payload.tag_id = 1` que o
     * Dashboard mandava fixo.
     */
    public function test_lead_novo_cai_no_funil_padrao_sem_id_chutado(): void
    {
        $this->actingAs($this->staff)
            ->postJson('/api/usuarios', [
                'nome' => 'Cliente Novo',
                'email' => 'cliente.novo@example.com',
                'telefone' => '11999999999',
            ])
            ->assertCreated();

        $novo = Usuario::where('email', 'cliente.novo@example.com')->first();

        $this->assertSame($this->vendas->id, $novo->funil_id);
        $this->assertSame($this->estagioVendas->id, $novo->estagio_id);
    }
}
