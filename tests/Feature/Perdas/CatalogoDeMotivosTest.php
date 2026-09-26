<?php

namespace Tests\Feature\Perdas;

use App\Models\MotivoPerda;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * O catálogo de motivos e a invariante que o protege.
 *
 * A invariante é da mesma família de "todo funil precisa de um estágio aberto":
 * **o tenant precisa de ao menos um motivo disponível**. Arquivar o último
 * tornaria impossível perder qualquer coisa, e a falha apareceria longe daqui —
 * no vendedor arrastando um card e recebendo um erro que ele não pode resolver.
 */
class CatalogoDeMotivosTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->vendedor = User::factory()->create(['tenant_id' => $this->tenant->id]);

        app(CurrentTenant::class)->set($this->tenant);

        setPermissionsTeamId($this->tenant->id);
        $this->admin->assignRole('admin');
        $this->vendedor->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_vendedor_le_mas_nao_escreve(): void
    {
        MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id]);

        // Precisa ler: é ele quem arrasta o card e escolhe o motivo no modal.
        $this->actingAs($this->vendedor)->getJson('/api/motivos-perda')->assertOk();

        $this->actingAs($this->vendedor)
            ->postJson('/api/motivos-perda', ['descricao' => 'Inventado'])
            ->assertForbidden();

        $this->assertDatabaseMissing('motivos_perda', ['descricao' => 'Inventado']);
    }

    public function test_ultimo_motivo_nao_pode_ser_arquivado(): void
    {
        $unico = MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/motivos-perda/{$unico->id}")
            ->assertStatus(422);

        $this->assertNull($unico->fresh()->deleted_at);
    }

    public function test_motivo_sai_quando_sobra_outro(): void
    {
        $um = MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id]);
        MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/motivos-perda/{$um->id}")
            ->assertOk();

        $this->assertNotNull($um->fresh()->deleted_at);
    }

    public function test_arquivado_some_do_seletor_mas_volta_na_configuracao(): void
    {
        $arquivado = MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id]);
        MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin)->deleteJson("/api/motivos-perda/{$arquivado->id}")->assertOk();

        // Seletor de quem registra uma perda: não oferece o aposentado.
        $seletor = $this->actingAs($this->admin)->getJson('/api/motivos-perda')->assertOk()->json();
        $this->assertNotContains($arquivado->id, array_column($seletor, 'id'));

        // Tela de configuração: mostra, para poder restaurar.
        $config = $this->actingAs($this->admin)
            ->getJson('/api/motivos-perda?incluir_arquivados=1')
            ->assertOk()
            ->json();
        $linha = collect($config)->firstWhere('id', $arquivado->id);
        $this->assertNotNull($linha);
        $this->assertTrue($linha['arquivado']);

        $this->actingAs($this->admin)
            ->patchJson("/api/motivos-perda/{$arquivado->id}/restaurar")
            ->assertOk();

        $this->assertNull($arquivado->fresh()->deleted_at);
    }

    /**
     * Dois motivos com o mesmo nome produzem duas linhas no relatório que
     * deveriam ser uma. O erro é de leitura, não de gravação, e só aparece
     * quando alguém tenta somar.
     */
    public function test_nome_duplicado_e_recusado(): void
    {
        MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id, 'descricao' => 'Preço']);

        $this->actingAs($this->admin)
            ->postJson('/api/motivos-perda', ['descricao' => 'Preço'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('descricao');
    }

    public function test_motivo_de_outro_tenant_nao_e_alcancavel(): void
    {
        $outroTenant = Tenant::factory()->create();
        $alheio = MotivoPerda::factory()->create(['tenant_id' => $outroTenant->id]);

        $this->actingAs($this->admin)
            ->putJson("/api/motivos-perda/{$alheio->id}", ['descricao' => 'Sequestrado'])
            ->assertNotFound();

        $this->assertSame($alheio->descricao, $alheio->fresh()->descricao);
    }

    public function test_tenant_novo_nasce_com_catalogo(): void
    {
        $this->post('/register', [
            'name' => 'Empresa Nova',
            'email' => 'empresa.nova@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();

        // Sem catálogo, a primeira tentativa de perder travaria sem saída: não
        // há como cadastrar um motivo no meio do fluxo de arrastar um card.
        $motivos = $this->getJson('/api/motivos-perda')->assertOk()->json();
        $this->assertNotEmpty($motivos);
    }
}
