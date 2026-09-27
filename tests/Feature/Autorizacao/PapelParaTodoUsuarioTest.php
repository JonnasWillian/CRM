<?php

namespace Tests\Feature\Autorizacao;

use App\Models\Funil;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Usuário sem papel nenhum é uma conta INCOERENTE, não uma conta restrita.
 *
 * O seed de RBAC deu `admin` só ao primeiro usuário de cada tenant. Enquanto
 * as policies não existiam, isso "não limitava em nada". Depois que elas
 * entraram, o usuário sem papel passou a viver o seguinte, no PRÓPRIO lead:
 *
 *   abre a tela        -> 200
 *   salva a edição     -> negado  (não tem `leads.manage`)
 *   cria tarefa nele   -> 201     (tarefa só checa a policy de instância)
 *   anota nele         -> 201     (idem)
 *
 * Nenhuma dessas quatro respostas é errada isoladamente; juntas são um estado
 * que ninguém desenhou. A correção é dar papel a quem não tem — o backfill de
 * 2026_09_27_100001 — e não afrouxar as policies.
 */
class PapelParaTodoUsuarioTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_09_27_100001_backfill_papel_vendedor_para_usuarios_sem_papel.php';

    private function backfill(): void
    {
        // `require` e não Artisan::call('migrate'): o RefreshDatabase já rodou
        // todas as migrations, e os usuários deste teste nascem DEPOIS delas —
        // que é exatamente a situação do banco real antes do deploy.
        (require base_path(self::MIGRATION))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function orfao(Tenant $tenant): User
    {
        $usuario = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertDatabaseMissing('model_has_roles', [
            'model_type' => User::class, 'model_id' => $usuario->id,
        ]);

        return $usuario;
    }

    public function test_o_backfill_da_vendedor_a_quem_esta_sem_papel(): void
    {
        $tenant = Tenant::factory()->create();
        app(CurrentTenant::class)->set($tenant);
        $orfao = $this->orfao($tenant);

        $this->backfill();

        $vendedorId = DB::table('roles')->where('name', 'vendedor')->whereNull('tenant_id')->value('id');

        // A atribuição carrega o tenant_id do USUÁRIO (modo teams); o papel em
        // si é global. Trocar os dois é o erro clássico aqui.
        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $vendedorId,
            'model_type' => User::class,
            'model_id' => $orfao->id,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_o_backfill_nao_mexe_em_quem_ja_tem_papel(): void
    {
        $tenant = Tenant::factory()->create();
        app(CurrentTenant::class)->set($tenant);

        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        setPermissionsTeamId($tenant->id);
        $admin->assignRole('admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->backfill();

        $papeis = DB::table('model_has_roles')
            ->where('model_type', User::class)->where('model_id', $admin->id)
            ->count();

        $this->assertSame(1, $papeis, 'quem ja era admin nao pode virar admin+vendedor');
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_o_backfill_e_idempotente(): void
    {
        $tenant = Tenant::factory()->create();
        app(CurrentTenant::class)->set($tenant);
        $orfao = $this->orfao($tenant);

        $this->backfill();
        $this->backfill();

        $this->assertSame(1, DB::table('model_has_roles')
            ->where('model_type', User::class)->where('model_id', $orfao->id)->count());
    }

    /**
     * O fecho: depois do backfill, as quatro respostas do bloco de cima
     * concordam entre si. É isto que prova que o defeito sumiu — não a
     * existência da linha em `model_has_roles`.
     */
    public function test_depois_do_backfill_a_conta_fica_coerente_no_proprio_lead(): void
    {
        $tenant = Tenant::factory()->create();
        app(CurrentTenant::class)->set($tenant);
        $orfao = $this->orfao($tenant);

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $lead = Usuario::factory()->create([
            'tenant_id' => $tenant->id, 'user_id' => $orfao->id, 'funil_id' => $funil->id,
        ]);

        // Antes: abre a tela (200) mas nao salva.
        $this->actingAs($orfao)->get("/leads/{$lead->id}")->assertOk();
        $this->actingAs($orfao)
            ->putJson("/api/usuarios/{$lead->id}", [
                'nome' => 'Nome Editado', 'email' => $lead->email, 'telefone' => '11999998888',
            ])
            ->assertForbidden();

        $this->backfill();
        setPermissionsTeamId($tenant->id);

        $this->actingAs($orfao->fresh())->get("/leads/{$lead->id}")->assertOk();
        $this->actingAs($orfao->fresh())
            ->putJson("/api/usuarios/{$lead->id}", [
                'nome' => 'Nome Editado', 'email' => $lead->email, 'telefone' => '11999998888',
            ])
            ->assertOk();
        $this->actingAs($orfao->fresh())
            ->postJson('/api/tarefas', [
                'usuario_id' => $lead->id, 'titulo' => 'Ligar', 'data_limite' => now()->addDay()->toDateString(),
            ])
            ->assertCreated();
        $this->actingAs($orfao->fresh())
            ->postJson('/api/anotacao', ['usuario_id' => $lead->id, 'descricao' => 'Primeiro contato'])
            ->assertCreated();

        $this->assertSame('Nome Editado', $lead->fresh()->nome);
    }

    /**
     * `vendedor` e não `admin`: o backfill não pode promover ninguém. Quem
     * estava sem papel não administra configuração do tenant nem enxerga a
     * carteira dos colegas.
     */
    public function test_o_backfill_nao_promove_ninguem(): void
    {
        $tenant = Tenant::factory()->create();
        app(CurrentTenant::class)->set($tenant);
        $orfao = $this->orfao($tenant);

        $this->backfill();
        setPermissionsTeamId($tenant->id);
        $orfao = $orfao->fresh();

        $this->assertTrue($orfao->hasRole('vendedor'));
        $this->assertFalse($orfao->can('configuracoes.manage'));
        $this->assertFalse($orfao->can('leads.view-all'));
        $this->assertFalse($orfao->can('agentes.manage'));
    }

    /**
     * Item 5 da rodada C: o early-return de "papel não encontrado" cobria só
     * o caso de seed revertido. Um RENAME de `vendedor` (ex.: para
     * `consultor`) cai no mesmo `if`, e antes desta correção passava em
     * silêncio total — o órfão continuava órfão e nada acusava o motivo.
     */
    public function test_backfill_avisa_e_nao_assina_nada_se_vendedor_for_renomeado(): void
    {
        $tenant = Tenant::factory()->create();
        app(CurrentTenant::class)->set($tenant);
        $orfao = $this->orfao($tenant);

        // Simula o rename: mesma linha de `roles`, nome novo.
        DB::table('roles')->where('name', 'vendedor')->whereNull('tenant_id')->update(['name' => 'consultor']);

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $mensagem) => str_contains($mensagem, 'vendedor') && str_contains($mensagem, 'renomeado'));

        $this->backfill();

        $this->assertDatabaseMissing('model_has_roles', [
            'model_type' => User::class, 'model_id' => $orfao->id,
        ]);
    }

    /**
     * Item 6 da rodada C: a subquery `whereNotExists` perguntava "existe
     * alguma linha em model_has_roles para este model_id?" sem olhar
     * tenant_id. No modo teams, um usuário com papel registrado em OUTRO
     * tenant contaria como "já tem papel" e seria pulado — ficando sem
     * atribuição no tenant dele.
     *
     * O cenário abaixo é hoje INALCANÇÁVEL pelos fluxos da aplicação
     * (`users.tenant_id` é NOT NULL e único por usuário: não existe usuário
     * compartilhado entre tenants) — a linha em model_has_roles com
     * tenant_id de outro tenant é inserida direto no banco só para provar que
     * a subquery é correta por conta própria, sem depender dessa garantia
     * externa continuar valendo para sempre.
     */
    public function test_backfill_nao_pula_usuario_com_papel_registrado_em_outro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        app(CurrentTenant::class)->set($tenantA);
        $usuario = User::factory()->create(['tenant_id' => $tenantA->id]);

        $papelDeOutroTenant = DB::table('roles')->where('name', 'admin')->whereNull('tenant_id')->value('id');
        DB::table('model_has_roles')->insert([
            'role_id' => $papelDeOutroTenant,
            'model_type' => User::class,
            'model_id' => $usuario->id,
            'tenant_id' => $tenantB->id,
        ]);

        $this->backfill();

        $vendedorId = DB::table('roles')->where('name', 'vendedor')->whereNull('tenant_id')->value('id');

        $this->assertDatabaseHas('model_has_roles', [
            'role_id' => $vendedorId,
            'model_type' => User::class,
            'model_id' => $usuario->id,
            'tenant_id' => $tenantA->id,
        ]);
    }
}
