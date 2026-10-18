<?php

namespace Tests\Feature\Equipe;

use App\Mail\ConviteDeEquipeMail;
use App\Models\Convite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/** Equipe: listar, convidar, aceitar convite, mudar papel, desativar e reativar. */
class EquipeTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_admin_lista_a_equipe_e_vendedor_nao(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $ana = $this->agente($tenant);
        $this->lead($tenant, $ana);
        $this->lead($tenant, $ana);

        $dados = $this->actingAs($admin)->getJson('/api/equipe')->assertOk()->json('data');
        $linhaAna = collect($dados)->firstWhere('id', $ana->id);
        $this->assertSame('vendedor', $linhaAna['papel']);
        $this->assertSame(2, $linhaAna['leads_count']);
        $this->assertTrue($linhaAna['ativo']);

        $this->actingAs($ana)->getJson('/api/equipe')->assertForbidden();
        $this->actingAs($ana)->get('/configuracoes/equipe')->assertForbidden();

        $this->actingAs($admin)->get('/configuracoes/equipe')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Configuracoes/Equipe')
            ->has('membros', 2)
            ->has('convites', 0)
            ->has('papeis', 3));
    }

    public function test_convidar_envia_email_e_aceitar_cria_o_membro_com_o_papel(): void
    {
        Mail::fake();
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');

        $this->actingAs($admin)->postJson('/api/equipe/convites', ['email' => 'nova@exemplo.test', 'papel' => 'gestor'])
            ->assertCreated()->assertJsonPath('data.email', 'nova@exemplo.test');

        $token = null;
        Mail::assertQueued(ConviteDeEquipeMail::class, function (ConviteDeEquipeMail $mail) use (&$token) {
            $token = $mail->token;

            return $mail->hasTo('nova@exemplo.test');
        });

        // E-mail já usado por outra conta: recusado.
        $this->actingAs($admin)->postJson('/api/equipe/convites', ['email' => $admin->email, 'papel' => 'vendedor'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');

        auth()->logout();
        $this->get("/convites/{$token}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Equipe/AceitarConvite')
            ->where('convite.email', 'nova@exemplo.test')
            ->where('convite.papel', 'gestor')
            ->where('convite.estado', 'pendente'));

        $this->post("/convites/{$token}", ['name' => 'Nova Pessoa', 'password' => 'SenhaForte#2026', 'password_confirmation' => 'SenhaForte#2026'])
            ->assertRedirect('/dashboard');

        $nova = User::firstWhere('email', 'nova@exemplo.test');
        $this->assertSame($tenant->id, $nova->tenant_id);
        $this->assertAuthenticatedAs($nova);
        setPermissionsTeamId($tenant->id);
        $this->assertTrue($nova->fresh()->hasRole('gestor'));

        // Usado: não serve de novo.
        auth()->logout();
        $this->get("/convites/{$token}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('convite.estado', 'usado'));
        $this->post("/convites/{$token}", ['name' => 'Outra', 'password' => 'SenhaForte#2026', 'password_confirmation' => 'SenhaForte#2026'])->assertStatus(410);
    }

    public function test_convite_expirado_ou_cancelado_e_token_invalido(): void
    {
        Mail::fake();
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $id = $this->actingAs($admin)->postJson('/api/equipe/convites', ['email' => 'x@exemplo.test', 'papel' => 'vendedor'])->json('data.id');
        $token = null;
        Mail::assertQueued(ConviteDeEquipeMail::class, function ($m) use (&$token) {
            $token = $m->token;

            return true;
        });

        $this->actingAs($admin)->deleteJson("/api/equipe/convites/{$id}")->assertOk();
        auth()->logout();
        $this->get("/convites/{$token}")->assertInertia(fn (Assert $page) => $page->where('convite.estado', 'cancelado'));
        $this->get('/convites/'.str_repeat('a', 40))->assertNotFound();

        Convite::query()->update(['cancelado_em' => null, 'expira_em' => now()->subDay()]);
        $this->get("/convites/{$token}")->assertInertia(fn (Assert $page) => $page->where('convite.estado', 'expirado'));
    }

    public function test_reenviar_gera_novo_token_e_estende_validade(): void
    {
        Mail::fake();
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $id = $this->actingAs($admin)->postJson('/api/equipe/convites', ['email' => 'y@exemplo.test', 'papel' => 'vendedor'])->json('data.id');
        $antes = Convite::find($id)->token_hash;

        $this->actingAs($admin)->postJson("/api/equipe/convites/{$id}/reenviar")->assertOk();
        $this->assertNotSame($antes, Convite::find($id)->token_hash);
        Mail::assertQueued(ConviteDeEquipeMail::class, 2);
    }

    public function test_alterar_papel_com_regra_do_ultimo_admin(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $ana = $this->agente($tenant);

        $this->actingAs($admin)->patchJson("/api/equipe/{$ana->id}/papel", ['papel' => 'gestor'])->assertOk();
        setPermissionsTeamId($tenant->id);
        $this->assertTrue($ana->fresh()->hasRole('gestor'));
        $this->assertFalse($ana->fresh()->hasRole('vendedor'));

        $this->actingAs($admin)->patchJson("/api/equipe/{$admin->id}/papel", ['papel' => 'vendedor'])
            ->assertUnprocessable()->assertJsonPath('message', 'Você não pode mudar o seu próprio papel.');
    }

    public function test_desativar_bloqueia_com_leads_e_transfere_a_carteira_quando_pedido(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $ana = $this->agente($tenant);
        $bia = $this->agente($tenant);
        $lead = $this->lead($tenant, $ana);
        DB::table('sessions')->insert(['id' => 'sess-ana', 'user_id' => $ana->id, 'payload' => '', 'last_activity' => time()]);

        $this->actingAs($admin)->patchJson("/api/equipe/{$ana->id}/desativar")
            ->assertUnprocessable()->assertJsonValidationErrors('transferir_para');

        $this->actingAs($admin)->patchJson("/api/equipe/{$ana->id}/desativar", ['transferir_para' => $bia->id])->assertOk();
        $this->assertFalse($ana->fresh()->ativo);
        $this->assertSame($bia->id, $lead->fresh()->user_id);
        $this->assertDatabaseMissing('sessions', ['id' => 'sess-ana']);

        $this->actingAs($admin)->patchJson("/api/equipe/{$admin->id}/desativar")->assertUnprocessable();

        $this->actingAs($admin)->patchJson("/api/equipe/{$ana->id}/reativar")->assertOk();
        $this->assertTrue($ana->fresh()->ativo);
    }

    public function test_membro_de_outra_empresa_e_404(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $outro = $this->novoTenant();
        $alheio = $this->agente($outro);
        $this->ativar($tenant);

        $this->actingAs($admin)->patchJson("/api/equipe/{$alheio->id}/desativar")->assertNotFound();
        $this->actingAs($admin)->patchJson("/api/equipe/{$alheio->id}/papel", ['papel' => 'admin'])->assertNotFound();
    }

    public function test_opcoes_de_responsavel_para_quem_ve_todos(): void
    {
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $ana = $this->agente($tenant);
        $inativo = $this->agente($tenant);
        $inativo->forceFill(['ativo' => false])->save();

        $ids = array_column($this->actingAs($gestor)->getJson('/api/equipe/opcoes')->assertOk()->json(), 'id');
        $this->assertEqualsCanonicalizing([$gestor->id, $ana->id], $ids);
        $this->actingAs($ana)->getJson('/api/equipe/opcoes')->assertForbidden();
    }
}
