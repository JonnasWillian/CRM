<?php

namespace Tests\Feature\Seguranca;

use App\Jobs\ImportarLeads;
use App\Mail\AvisoDeAssinaturaMail;
use App\Mail\ConviteDeEquipeMail;
use App\Models\Convite;
use App\Models\Estagio;
use App\Models\EventoDeGateway;
use App\Models\Funil;
use App\Services\Assinaturas\Assinaturas;
use App\Services\Assinaturas\Faturamento;
use App\Services\Equipe\Convites;
use App\Services\Equipe\Membros;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/** Achados da auditoria de segurança de 24/11/2026, um teste por correção. */
class AuditoriaDeSegurancaTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_navegador_nao_recebe_cpf_cnpj_nem_dados_internos_da_empresa(): void
    {
        $t = $this->novoTenant();
        $t->forceFill(['documento' => '52998224725', 'asaas_cliente_id' => 'cus_segredo'])->save();
        $vendedor = $this->agente($t, 'vendedor');

        $html = $this->actingAs($vendedor)->get('/dashboard')->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->where('auth.user.id', $vendedor->id)->where('auth.user.name', $vendedor->name)->where('auth.user.email', $vendedor->email)
                ->has('auth.user.tour_estado')
                ->missing('auth.user.tenant')->missing('auth.user.roles')->missing('auth.user.permissions')->missing('auth.user.password'))
            ->getContent();

        $this->assertStringNotContainsString('52998224725', $html);
        $this->assertStringNotContainsString('cus_segredo', $html);
        $this->assertArrayNotHasKey('documento', $t->fresh()->toArray());
        $this->assertArrayNotHasKey('asaas_cliente_id', $t->fresh()->toArray());
    }

    public function test_link_de_pagamento_so_vai_para_quem_gerencia_a_assinatura(): void
    {
        $t = $this->novoTenant();
        app(Assinaturas::class)->da($t);
        $admin = $this->agente($t, 'admin');
        $vendedor = $this->agente($t, 'vendedor');
        app(Faturamento::class)->contratar($t, 'profissional', 'mensal')->forceFill(['cobranca_id' => 'pay_1', 'link_pagamento' => 'https://www.asaas.com/i/segredo'])->save();

        $this->actingAs($vendedor)->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('assinatura.fatura_pendente.link_pagamento', null));
        $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $p) => $p->where('assinatura.fatura_pendente.link_pagamento', 'https://www.asaas.com/i/segredo'));
    }

    public function test_nome_com_markdown_nao_vira_link_no_convite_nem_no_assunto(): void
    {
        $t = $this->novoTenant();
        $t->forceFill(['nome' => 'Banco [confirme seus dados](https://mal.test) ![x](https://mal.test/p.png)'])->save();
        $admin = $this->agente($t, 'admin');
        $admin->forceFill(['name' => "Ana [clique](https://mal.test)\r\nBcc: x@mal.test"])->save();
        $convite = new Convite(['email' => 'a@b.test', 'papel' => 'vendedor', 'convidado_por' => $admin->id]);
        $convite->tenant_id = $t->id;
        $convite->expira_em = now()->addDays(7);

        $mail = new ConviteDeEquipeMail($convite, str_repeat('a', 48), $t->nome, $admin->name);
        $html = $mail->render();

        $this->assertStringNotContainsString('href="https://mal.test', $html);
        $this->assertStringNotContainsString('src="https://mal.test', $html);
        $this->assertStringNotContainsString('mal.test', $mail->envelope()->subject);
        $this->assertStringNotContainsString("\n", $mail->envelope()->subject);
        $this->assertInstanceOf(ShouldBeEncrypted::class, $mail);
    }

    public function test_nome_da_empresa_com_markdown_nao_vira_link_nos_avisos_ao_comercial(): void
    {
        $mail = new AvisoDeAssinaturaMail('comercial_perfil', ['empresa' => '[pague aqui](https://mal.test)', 'plano' => 'Inicial', 'linhas' => ['Ramo: Software']]);

        $this->assertStringNotContainsString('href="https://mal.test', $mail->render());
        $this->assertStringNotContainsString('mal.test', $mail->envelope()->subject);
    }

    public function test_webhook_recusa_token_curto_e_guarda_so_o_necessario_do_aviso(): void
    {
        config(['services.asaas.token_webhook' => 'curto']);
        $this->postJson('/api/webhooks/asaas', ['id' => 'e1', 'event' => 'X'], ['asaas-access-token' => 'curto'])->assertNotFound();

        $token = str_repeat('t', 40);
        config(['services.asaas.token_webhook' => $token, 'services.asaas.chave' => '']);
        $this->postJson('/api/webhooks/asaas', [
            'id' => 'evt_1', 'event' => 'PAYMENT_UPDATED',
            'payment' => ['id' => 'pay_x', 'externalReference' => 'fatura:1', 'status' => 'PENDING', 'value' => 10, 'customer' => 'cus_9', 'invoiceUrl' => 'https://asaas/i/x', 'creditCard' => ['creditCardNumber' => '8829', 'creditCardToken' => 'tok_secreto']],
        ], ['asaas-access-token' => $token])->assertOk();

        $payload = EventoDeGateway::firstOrFail()->payload;
        $this->assertEquals(['id' => 'pay_x', 'externalReference' => 'fatura:1', 'status' => 'PENDING', 'value' => 10], $payload['payment']);
        $this->assertEqualsCanonicalizing(['id', 'event', 'payment'], array_keys($payload));
        $this->assertStringNotContainsString('tok_secreto', json_encode($payload));
        $this->assertStringNotContainsString('8829', json_encode($payload));
    }

    public function test_toda_resposta_traz_os_cabecalhos_de_seguranca(): void
    {
        foreach ([$this->get('/login'), $this->actingAs($this->agente($this->novoTenant()))->get('/dashboard')] as $r) {
            $r->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
            $this->assertNotNull($r->headers->get('Permissions-Policy'));
            $this->assertNull($r->headers->get('Strict-Transport-Security')); // só em produção com https
        }
    }

    public function test_esqueci_a_senha_responde_igual_para_email_que_existe_ou_nao(): void
    {
        $u = $this->agente($this->novoTenant());
        Notification::fake();

        $existe = $this->post('/forgot-password', ['email' => $u->email]);
        $naoExiste = $this->post('/forgot-password', ['email' => 'ninguem@nada.test']);

        $existe->assertSessionHasNoErrors();
        $naoExiste->assertSessionHasNoErrors();
        $this->assertSame(session('status'), 'Se este e-mail tiver uma conta, enviamos o link para redefinir a senha.');
    }

    public function test_rotas_sensiveis_tem_limite_de_tentativas(): void
    {
        $com = fn (string $nome) => collect(Route::getRoutes()->getByName($nome)->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'throttle:') && $m !== 'throttle:api');

        foreach (['password.update', 'profile.destroy', 'api.equipe.convites.store', 'api.equipe.convites.reenviar', 'api.leads.exportar', 'api.importacoes.store', 'api.webhooks.asaas'] as $rota) {
            $this->assertTrue($com($rota), "{$rota} sem throttle próprio");
        }
        $confirmar = collect(Route::getRoutes()->getRoutes())->first(fn ($r) => $r->uri() === 'confirm-password' && in_array('POST', $r->methods()));
        $this->assertTrue(collect($confirmar->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'throttle:')));
    }

    public function test_lead_nao_aceita_estagio_de_outro_funil_nem_responsavel_desativado(): void
    {
        $t = $this->novoTenant();
        $gestor = $this->agente($t, 'gestor');
        $inativo = $this->agente($t, 'vendedor');
        $inativo->forceFill(['ativo' => false])->save();
        $lead = $this->lead($t, $gestor);
        $outro = Funil::factory()->create(['tenant_id' => $t->id]);
        $deOutroFunil = Estagio::factory()->create(['tenant_id' => $t->id, 'funil_id' => $outro->id]);

        $this->actingAs($gestor)->putJson("/api/leads/{$lead->id}", ['nome' => $lead->nome, 'email' => $lead->email, 'estagio_id' => $deOutroFunil->id])
            ->assertUnprocessable()->assertJsonValidationErrors('estagio_id');
        $this->actingAs($gestor)->postJson('/api/leads', ['nome' => 'Cliente Novo', 'email' => 'n@x.test', 'user_id' => $inativo->id])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
    }

    public function test_convites_pendentes_de_quem_foi_desativado_ou_rebaixado_sao_cancelados(): void
    {
        Mail::fake();
        $t = $this->novoTenant();
        $dono = $this->agente($t, 'admin');
        $outroAdmin = $this->agente($t, 'admin');
        $rebaixado = $this->agente($t, 'admin');
        $c1 = app(Convites::class)->convidar($outroAdmin, 'x1@x.test', 'admin');
        $c2 = app(Convites::class)->convidar($rebaixado, 'x2@x.test', 'admin');
        $meu = app(Convites::class)->convidar($dono, 'x3@x.test', 'vendedor');

        app(Membros::class)->desativar($dono, $outroAdmin, null);
        app(Membros::class)->alterarPapel($dono, $rebaixado, 'vendedor');

        $this->assertNotNull($c1->fresh()->cancelado_em);
        $this->assertNotNull($c2->fresh()->cancelado_em);
        $this->assertNull($meu->fresh()->cancelado_em);
    }

    public function test_fatura_de_troca_vencida_nao_pode_mais_ser_paga(): void
    {
        $t = $this->novoTenant();
        app(Assinaturas::class)->da($t);
        $troca = app(Faturamento::class)->gerar($t, 'profissional', 'mensal', 'troca', now()->subDays(3), 1, 10);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('venceu');
        app(Faturamento::class)->pagar($troca, 'pay_1', 'asaas');
    }

    public function test_cookie_de_sessao_e_seguro_por_padrao_em_producao(): void
    {
        $config = file_get_contents(config_path('session.php'));
        $this->assertStringContainsString("env('SESSION_SECURE_COOKIE', env('APP_ENV') === 'production')", $config);
    }

    public function test_importacao_no_worker_nao_deixa_empresa_nem_usuario_ativos_para_o_proximo_job(): void
    {
        $t = $this->novoTenant();
        $gestor = $this->agente($t, 'gestor');
        Funil::factory()->padrao()->create(['tenant_id' => $t->id]);
        $arquivo = UploadedFile::fake()->createWithContent('l.csv', "nome;email\nCliente Novo;novo@ex.test\n");
        $id = $this->actingAs($gestor)->postJson('/api/importacoes', ['arquivo' => $arquivo])->json('data.id');
        Queue::fake();
        $this->actingAs($gestor)->postJson("/api/importacoes/{$id}/iniciar", ['mapeamento' => ['nome' => 0, 'email' => 1], 'opcoes' => ['duplicados' => 'ignorar']])->assertOk();

        // Como no worker: processo sem empresa nem usuário.
        app(CurrentTenant::class)->clear();
        setPermissionsTeamId(null);
        Auth::forgetUser();
        (new ImportarLeads($id))->handle();

        $this->assertFalse(app(CurrentTenant::class)->check());
        $this->assertNull(getPermissionsTeamId());
        $this->assertFalse(Auth::hasUser());
    }

    public function test_trocar_a_senha_derruba_as_outras_sessoes_tambem_nas_paginas(): void
    {
        $u = $this->agente($this->novoTenant());
        $this->post('/login', ['email' => $u->email, 'password' => 'password'])->assertRedirect();
        $this->get('/dashboard')->assertOk();

        // Em outro aparelho, a senha é trocada.
        $u->forceFill(['password' => Hash::make('OutraSenha#2026')])->save();
        Auth::forgetGuards();

        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_email_com_quebra_de_linha_e_recusado_no_cadastro_e_no_convite(): void
    {
        $this->post('/register', ['name' => 'Ana Souza', 'empresa' => 'Acme', 'email' => "ana@ex.test\r\nbcc: x@mal.test", 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $admin = $this->agente($this->novoTenant(), 'admin');
        $this->actingAs($admin)->postJson('/api/equipe/convites', ['email' => "novo@ex.test\nbcc: x@mal.test", 'papel' => 'vendedor'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}
