<?php

namespace Tests\Feature\Funis;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A tela de configuração de funis e as invariantes que o backend protege.
 *
 * As três que importam, e o que cada uma evita:
 *
 * - Só quem tem `configuracoes.manage` escreve. Sem isso, qualquer vendedor
 *   renomearia os estágios da empresa inteira.
 * - Todo funil mantém ao menos um estágio `aberto`. Sem isso, o cadastro de
 *   lead falha longe daqui, no momento em que alguém tenta usar o funil.
 * - O funil padrão e funis com leads não são arquiváveis. Sem isso, sobram
 *   leads apontando para um funil que nenhuma tela mostra.
 */
class ConfiguracaoDeFunisTest extends TestCase
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

    public function test_agente_sem_configuracoes_manage_nao_escreve(): void
    {
        $this->actingAs($this->vendedor)
            ->postJson('/api/funis', ['nome' => 'Pós-venda'])
            ->assertForbidden();

        $this->assertDatabaseMissing('funis', ['nome' => 'Pós-venda']);
    }

    public function test_agente_sem_permissao_ainda_lista_funis(): void
    {
        Funil::factory()->create(['tenant_id' => $this->tenant->id]);

        // A leitura é aberta de propósito: o Kanban e o cadastro de lead
        // precisam saber quais funis existem, e quem usa essas telas não
        // necessariamente administra a configuração.
        $this->actingAs($this->vendedor)->getJson('/api/funis')->assertOk();
    }

    public function test_funil_novo_nasce_com_um_estagio_aberto(): void
    {
        $resposta = $this->actingAs($this->admin)
            ->postJson('/api/funis', ['nome' => 'Pós-venda'])
            ->assertCreated()
            ->json();

        // Um funil sem estágio aberto não recebe lead nenhum: ele seria criado
        // e só falharia no primeiro uso.
        $this->assertCount(1, $resposta['estagios']);
        $this->assertSame(Estagio::TIPO_ABERTO, $resposta['estagios'][0]['tipo']);
    }

    public function test_primeiro_funil_do_tenant_vira_padrao(): void
    {
        $resposta = $this->actingAs($this->admin)
            ->postJson('/api/funis', ['nome' => 'Vendas'])
            ->assertCreated()
            ->json();

        $this->assertTrue($resposta['is_default']);
    }

    public function test_definir_padrao_desmarca_o_anterior(): void
    {
        $primeiro = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
        $segundo = Funil::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin)
            ->patchJson("/api/funis/{$segundo->id}/padrao")
            ->assertOk();

        $this->assertFalse($primeiro->fresh()->is_default);
        $this->assertTrue($segundo->fresh()->is_default);
    }

    public function test_funil_padrao_nao_pode_ser_arquivado(): void
    {
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/funis/{$funil->id}")
            ->assertStatus(422);

        $this->assertNull($funil->fresh()->deleted_at);
    }

    public function test_funil_com_leads_nao_pode_ser_arquivado(): void
    {
        $funil = Funil::factory()->create(['tenant_id' => $this->tenant->id]);
        Usuario::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'funil_id' => $funil->id,
        ]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/funis/{$funil->id}")
            ->assertStatus(422);

        $this->assertNull($funil->fresh()->deleted_at);
    }

    public function test_ultimo_estagio_aberto_do_funil_nao_pode_ser_arquivado(): void
    {
        $funil = Funil::factory()->create(['tenant_id' => $this->tenant->id]);
        $unico = Estagio::factory()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);
        Estagio::factory()->ganho()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/estagios/{$unico->id}")
            ->assertStatus(422);

        $this->assertNull($unico->fresh()->deleted_at);
    }

    public function test_ultimo_estagio_aberto_nao_pode_mudar_de_tipo(): void
    {
        $funil = Funil::factory()->create(['tenant_id' => $this->tenant->id]);
        $unico = Estagio::factory()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);

        $this->actingAs($this->admin)
            ->putJson("/api/estagios/{$unico->id}", [
                'descricao' => $unico->descricao,
                'tipo' => Estagio::TIPO_GANHO,
            ])
            ->assertStatus(422);

        $this->assertSame(Estagio::TIPO_ABERTO, $unico->fresh()->tipo);
    }

    public function test_estagio_sai_quando_sobra_outro_aberto(): void
    {
        $funil = Funil::factory()->create(['tenant_id' => $this->tenant->id]);
        $um = Estagio::factory()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);
        Estagio::factory()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);

        $this->actingAs($this->admin)
            ->deleteJson("/api/estagios/{$um->id}")
            ->assertOk();

        $this->assertNotNull($um->fresh()->deleted_at);
    }

    /**
     * Um estágio arquivado continua aparecendo no Kanban enquanto segura leads.
     * Se ele sumisse da tela de configuração, o administrador veria uma coluna
     * no quadro que não encontra em lugar nenhum — e sem como restaurá-la.
     */
    public function test_estagio_arquivado_aparece_na_configuracao_e_pode_voltar(): void
    {
        $funil = Funil::factory()->create(['tenant_id' => $this->tenant->id]);
        $arquivado = Estagio::factory()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);
        Estagio::factory()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);

        $this->actingAs($this->admin)->deleteJson("/api/estagios/{$arquivado->id}")->assertOk();

        $listagem = $this->actingAs($this->admin)->getJson('/api/funis')->assertOk()->json();
        $doFunil = collect($listagem)->firstWhere('id', $funil->id);
        $linha = collect($doFunil['estagios'])->firstWhere('id', $arquivado->id);

        $this->assertNotNull($linha, 'estágio arquivado sumiu da tela de configuração');
        $this->assertTrue($linha['arquivada']);

        $this->actingAs($this->admin)
            ->patchJson("/api/estagios/{$arquivado->id}/restaurar")
            ->assertOk();

        $this->assertNull($arquivado->fresh()->deleted_at);
    }

    public function test_tipo_e_obrigatorio_e_fechado_na_lista(): void
    {
        $funil = Funil::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin)
            ->postJson("/api/funis/{$funil->id}/estagios", ['descricao' => 'Proposta enviada'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tipo');

        $this->actingAs($this->admin)
            ->postJson("/api/funis/{$funil->id}/estagios", ['descricao' => 'Proposta enviada', 'tipo' => 'arquivado'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tipo');
    }

    public function test_funil_de_outro_tenant_nao_e_alcancavel(): void
    {
        $outroTenant = Tenant::factory()->create();
        $alheio = Funil::factory()->create(['tenant_id' => $outroTenant->id]);

        $this->actingAs($this->admin)
            ->putJson("/api/funis/{$alheio->id}", ['nome' => 'Sequestrado'])
            ->assertNotFound();

        $this->assertSame($alheio->nome, $alheio->fresh()->nome);
    }
}
