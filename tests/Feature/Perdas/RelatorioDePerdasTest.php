<?php

namespace Tests\Feature\Perdas;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\MotivoPerda;
use App\Models\Perda;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * O relatório "por que perdemos".
 *
 * A propriedade que ele existe para ter: ler `perdas`, nunca o estado atual das
 * entidades. Um lead perdido em agosto e reaberto em setembro continua sendo
 * uma perda de agosto — é a diferença entre "por que perdemos em agosto" e "por
 * que os atualmente perdidos foram perdidos", e é a razão de `perdas` ser
 * tabela de eventos.
 */
class RelatorioDePerdasTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $gestor;
    private User $vendedor;
    private Funil $funil;
    private Estagio $aberto;
    private Estagio $perdido;

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

        $this->funil = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
        $this->aberto = Estagio::factory()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $this->funil->id]);
        $this->perdido = Estagio::factory()->perdido()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $this->funil->id]);
    }

    private function lead(): Usuario
    {
        return Usuario::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->gestor->id,
            'funil_id' => $this->funil->id,
            'estagio_id' => $this->aberto->id,
        ]);
    }

    private function perdaEm(Carbon $quando, MotivoPerda $motivo, ?float $valor = null): Perda
    {
        $perda = new Perda();
        $perda->motivo_perda_id = $motivo->id;
        $perda->valor = $valor;
        $perda->tenant_id = $this->tenant->id;
        $perda->created_at = $quando;

        $this->lead()->perdas()->save($perda);

        return $perda;
    }

    public function test_agrupa_por_motivo_com_contagem_valor_e_percentual(): void
    {
        $preco = MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id, 'descricao' => 'Preço']);
        $concorrente = MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id, 'descricao' => 'Concorrente']);

        $this->perdaEm(now()->subDays(3), $preco, 1000.00);
        $this->perdaEm(now()->subDays(2), $preco, 3000.00);
        $this->perdaEm(now()->subDay(), $concorrente, 500.00);

        $dados = $this->actingAs($this->gestor)->getJson('/api/relatorios/perdas')->assertOk()->json();

        $this->assertSame(3, $dados['total']);
        $this->assertEqualsWithDelta(4500.00, $dados['valor_total'], 0.01);

        // Ordenado do motivo que mais perde para o que menos perde — é a
        // primeira pergunta que o relatório responde.
        $this->assertSame('Preço', $dados['por_motivo'][0]['descricao']);
        $this->assertSame(2, $dados['por_motivo'][0]['total']);
        $this->assertEqualsWithDelta(4000.00, $dados['por_motivo'][0]['valor'], 0.01);
        $this->assertEqualsWithDelta(66.7, $dados['por_motivo'][0]['percentual'], 0.05);

        $this->assertSame('Concorrente', $dados['por_motivo'][1]['descricao']);
        $this->assertEqualsWithDelta(33.3, $dados['por_motivo'][1]['percentual'], 0.05);
    }

    public function test_periodo_recorta_as_perdas(): void
    {
        $motivo = MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->perdaEm(Carbon::parse('2026-08-10'), $motivo);
        $this->perdaEm(Carbon::parse('2026-09-10'), $motivo);

        $agosto = $this->actingAs($this->gestor)
            ->getJson('/api/relatorios/perdas?de=2026-08-01&ate=2026-08-31')
            ->assertOk()
            ->json();

        $this->assertSame(1, $agosto['total']);
    }

    /**
     * O caso que justifica a tabela de eventos. Com o motivo numa coluna da
     * entidade, reabrir apagaria a perda e agosto ficaria mentindo.
     */
    public function test_lead_reaberto_continua_contando_no_periodo_da_perda(): void
    {
        $motivo = MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id]);
        $lead = $this->lead();

        Carbon::setTestNow(Carbon::parse('2026-08-15 10:00:00'));
        $this->actingAs($this->gestor)->patchJson("/api/usuarios/{$lead->id}/estagio", [
            'estagio_id' => $this->perdido->id,
            'perda' => ['motivo_perda_id' => $motivo->id],
        ])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00'));
        $this->actingAs($this->gestor)->patchJson("/api/usuarios/{$lead->id}/estagio", [
            'estagio_id' => $this->aberto->id,
        ])->assertOk();

        // O lead está aberto HOJE, e mesmo assim agosto continua contando a
        // perda que aconteceu lá.
        $this->assertFalse($lead->fresh()->estadoAtualEhPerda());

        $agosto = $this->actingAs($this->gestor)
            ->getJson('/api/relatorios/perdas?de=2026-08-01&ate=2026-08-31')
            ->assertOk()
            ->json();

        $this->assertSame(1, $agosto['total']);

        Carbon::setTestNow();
    }

    public function test_separa_lead_de_projeto(): void
    {
        $motivo = MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->perdaEm(now()->subDay(), $motivo);
        $this->perdaEm(now()->subDay(), $motivo);

        $dados = $this->actingAs($this->gestor)->getJson('/api/relatorios/perdas')->assertOk()->json();

        $this->assertSame(2, $dados['por_tipo']['lead']);
        $this->assertSame(0, $dados['por_tipo']['projeto']);

        $soProjetos = $this->actingAs($this->gestor)
            ->getJson('/api/relatorios/perdas?tipo=projeto')
            ->assertOk()
            ->json();

        $this->assertSame(0, $soProjetos['total']);
    }

    /**
     * O relatório é o consolidado da empresa, que é justamente o que
     * `leads.view-all` separa de quem só enxerga a própria carteira.
     */
    public function test_vendedor_nao_ve_o_consolidado_do_tenant(): void
    {
        $this->actingAs($this->vendedor)->getJson('/api/relatorios/perdas')->assertForbidden();
        $this->actingAs($this->gestor)->getJson('/api/relatorios/perdas')->assertOk();
    }

    public function test_motivo_arquivado_continua_nomeando_as_perdas_antigas(): void
    {
        $motivo = MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id, 'descricao' => 'Sem verba']);
        MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->perdaEm(now()->subDay(), $motivo);
        $motivo->delete();

        $dados = $this->actingAs($this->gestor)->getJson('/api/relatorios/perdas')->assertOk()->json();

        $this->assertSame('Sem verba', $dados['por_motivo'][0]['descricao']);
        $this->assertTrue($dados['por_motivo'][0]['arquivado']);
    }

    public function test_perdas_nao_vazam_entre_tenants(): void
    {
        $motivo = MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->perdaEm(now()->subDay(), $motivo);

        $outroTenant = Tenant::factory()->create();
        $outroGestor = User::factory()->create(['tenant_id' => $outroTenant->id]);
        setPermissionsTeamId($outroTenant->id);
        $outroGestor->assignRole('gestor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $dados = $this->actingAs($outroGestor)->getJson('/api/relatorios/perdas')->assertOk()->json();

        $this->assertSame(0, $dados['total']);
    }
}
