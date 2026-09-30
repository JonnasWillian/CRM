<?php

namespace Tests\Feature\Autorizacao;

use App\Models\Funil;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A distinção que carrega o sistema: `leads.view-all` separa quem enxerga a
 * carteira inteira da empresa de quem enxerga só a própria.
 */
class VisibilidadeDeLeadsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $gestor;
    private User $vendedor;
    private Usuario $leadDoGestor;
    private Usuario $leadDoVendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->gestor = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->vendedor = User::factory()->create(['tenant_id' => $this->tenant->id]);

        app(CurrentTenant::class)->set($this->tenant);
        setPermissionsTeamId($this->tenant->id);
        $this->gestor->assignRole('gestor');
        $this->vendedor->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);

        $this->leadDoGestor = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->gestor->id, 'funil_id' => $funil->id,
        ]);
        $this->leadDoVendedor = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->vendedor->id, 'funil_id' => $funil->id,
        ]);
    }

    public function test_vendedor_lista_apenas_a_propria_carteira(): void
    {
        $ids = collect($this->actingAs($this->vendedor)->getJson('/api/leads')->assertOk()->json('data'))
            ->pluck('id')->all();

        $this->assertContains($this->leadDoVendedor->id, $ids);
        $this->assertNotContains($this->leadDoGestor->id, $ids);
    }

    public function test_gestor_lista_a_carteira_inteira_do_tenant(): void
    {
        $ids = collect($this->actingAs($this->gestor)->getJson('/api/leads')->assertOk()->json('data'))
            ->pluck('id')->all();

        $this->assertContains($this->leadDoGestor->id, $ids);
        $this->assertContains($this->leadDoVendedor->id, $ids, 'gestor tem leads.view-all e deve ver a equipe');
    }

    public function test_o_kanban_segue_a_mesma_regra(): void
    {
        $dados = $this->actingAs($this->gestor)->getJson('/api/kanban')->assertOk()->json();
        $ids = array_column($dados['leads'], 'id');

        $this->assertContains($this->leadDoVendedor->id, $ids);
    }

    public function test_as_metricas_seguem_a_mesma_regra(): void
    {
        $vendedor = $this->actingAs($this->vendedor)->getJson('/api/metricas')->assertOk()->json();
        $gestor = $this->actingAs($this->gestor)->getJson('/api/metricas')->assertOk()->json();

        $this->assertSame(1, $vendedor['total_leads']);
        $this->assertSame(2, $gestor['total_leads']);
    }
}
