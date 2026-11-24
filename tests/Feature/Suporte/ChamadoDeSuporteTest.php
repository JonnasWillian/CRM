<?php

namespace Tests\Feature\Suporte;

use App\Mail\ChamadoDeSuporteMail;
use App\Models\ChamadoDeSuporte;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as Pedido;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Assistente de ajuda e feedback: toda resposta fica gravada; o e-mail ao
 * suporte só sai quando a pessoa pede. Nome e e-mail da conta vêm do
 * servidor, nunca do formulário.
 */
class ChamadoDeSuporteTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['suporte.email' => 'contato@devscody.com']);
    }

    private function dados(array $extra = []): array
    {
        return array_merge([
            'tipo' => 'problema', 'area' => 'pipeline', 'impacto' => 'atrapalha',
            'mensagem' => 'O cartão não muda de estágio quando arrasto.',
            'acionar_suporte' => true, 'pagina' => '/kanban',
        ], $extra);
    }

    public function test_acionar_o_suporte_grava_e_envia_email_com_os_dados_da_conta(): void
    {
        $t = $this->novoTenant();
        $u = $this->agente($t, 'admin');

        $r = $this->actingAs($u)->postJson(route('api.suporte.store'), $this->dados())->assertCreated();

        $chamado = ChamadoDeSuporte::firstOrFail();
        $r->assertJson(['protocolo' => $chamado->id, 'suporte_acionado' => true, 'email_contato' => $u->email]);
        $this->assertSame($u->id, $chamado->user_id);
        $this->assertSame($t->id, $chamado->tenant_id);
        $this->assertSame($u->email, $chamado->email_contato);

        Mail::assertQueued(ChamadoDeSuporteMail::class, function (ChamadoDeSuporteMail $m) use ($u, $t) {
            $html = $m->render();

            return $m->hasTo('contato@devscody.com') && $m->hasReplyTo($u->email)
                && str_contains($html, e($u->name)) && str_contains($html, $u->email) && str_contains($html, e($t->nome))
                && str_contains($html, 'O cartão não muda de estágio quando arrasto.');
        });
    }

    public function test_sem_acionar_o_suporte_so_grava(): void
    {
        $u = $this->agente($this->novoTenant());

        $this->actingAs($u)->postJson(route('api.suporte.store'), $this->dados(['tipo' => 'sugestao', 'impacto' => null, 'nota' => 9, 'acionar_suporte' => false]))
            ->assertCreated()->assertJsonPath('suporte_acionado', false);

        $this->assertSame(9, ChamadoDeSuporte::first()->nota);
        Mail::assertNothingQueued();
    }

    public function test_email_de_contato_diferente_vira_o_reply_to_mas_a_conta_vem_do_servidor(): void
    {
        $u = $this->agente($this->novoTenant());

        $this->actingAs($u)->postJson(route('api.suporte.store'), $this->dados([
            'email_contato' => 'financeiro@acme.test', 'name' => 'Outro Nome', 'email' => 'forjado@x.test', 'user_id' => 999,
        ]))->assertCreated();

        Mail::assertQueued(ChamadoDeSuporteMail::class, function (ChamadoDeSuporteMail $m) use ($u) {
            $html = $m->render();

            return $m->hasReplyTo('financeiro@acme.test') && str_contains($html, $u->email)
                && ! str_contains($html, 'forjado@x.test') && ! str_contains($html, 'Outro Nome');
        });
        $this->assertSame($u->id, ChamadoDeSuporte::first()->user_id);
    }

    public function test_texto_do_cliente_sai_escapado_e_fora_do_assunto(): void
    {
        $u = $this->agente($this->novoTenant());
        $ataque = "<script>alert(1)</script> [clique](http://mal.test) <img src=x>\r\nBcc: x@mal.test";

        $this->actingAs($u)->postJson(route('api.suporte.store'), $this->dados(['mensagem' => $ataque]))->assertCreated();

        Mail::assertQueued(ChamadoDeSuporteMail::class, function (ChamadoDeSuporteMail $m) {
            $html = $m->render();

            return ! str_contains($html, '<script>') && ! str_contains($html, '<img src=x>') && ! str_contains($html, 'href="http://mal.test"')
                && str_contains($html, '&lt;script&gt;')
                && ! str_contains($m->envelope()->subject, 'alert') && ! str_contains($m->envelope()->subject, 'Bcc');
        });
    }

    public function test_validacao_em_portugues(): void
    {
        $u = $this->agente($this->novoTenant());

        $this->actingAs($u)->postJson(route('api.suporte.store'), ['tipo' => 'virus', 'area' => 'x', 'mensagem' => 'oi', 'email_contato' => 'nao-e-email', 'pagina' => 'javascript:alert(1)'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tipo', 'area', 'mensagem', 'email_contato', 'pagina'])
            ->assertJsonPath('errors.mensagem.0', 'Conte um pouco mais: pelo menos 10 caracteres.');
        $this->actingAs($u)->postJson(route('api.suporte.store'), $this->dados(['impacto' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('impacto');
        $this->assertSame(0, ChamadoDeSuporte::count());
    }

    public function test_visitante_nao_envia_e_ha_limite_por_pessoa(): void
    {
        $this->postJson(route('api.suporte.store'), $this->dados())->assertUnauthorized();

        $u = $this->agente($this->novoTenant());
        config(['suporte.maximo_por_dia' => 2]);
        $this->actingAs($u)->postJson(route('api.suporte.store'), $this->dados())->assertCreated();
        $this->actingAs($u)->postJson(route('api.suporte.store'), $this->dados())->assertCreated();
        $this->actingAs($u)->postJson(route('api.suporte.store'), $this->dados())
            ->assertStatus(429)->assertJsonPath('message', 'Você já enviou várias mensagens hoje. Se for urgente, escreva para contato@devscody.com.');
        $this->assertSame(2, ChamadoDeSuporte::count());
    }

    public function test_assinatura_bloqueada_ainda_fala_com_o_suporte(): void
    {
        config(['comercial.exigir_assinatura' => true]);
        $t = $this->novoTenant();
        $u = $this->agente($t, 'admin');
        $a = app(\App\Services\Assinaturas\Assinaturas::class)->da($t);
        $a->forceFill(['status' => 'teste', 'teste_termina_em' => now()->subDays(40)])->save();

        $this->actingAs($u)->postJson(route('api.suporte.store'), $this->dados(['tipo' => 'cobranca', 'area' => 'assinatura', 'impacto' => null]))->assertCreated();
    }

    public function test_canal_formsubmit_envia_pelo_servico_sem_smtp_nem_fila(): void
    {
        config(['suporte.canal' => 'formsubmit']);
        Http::fake(['formsubmit.co/*' => Http::response(['success' => 'true'])]);
        $t = $this->novoTenant();
        $u = $this->agente($t, 'admin');

        $this->actingAs($u)->postJson(route('api.suporte.store'), $this->dados(['email_contato' => 'fin@acme.test']))->assertCreated();

        $this->assertNotNull(ChamadoDeSuporte::first()->entregue_em);
        Mail::assertNothingQueued();
        Http::assertSent(function (Pedido $r) use ($u, $t) {
            return $r->url() === 'https://formsubmit.co/ajax/contato@devscody.com' && $r->method() === 'POST'
                && $r['_replyto'] === 'fin@acme.test' && $r['email'] === 'fin@acme.test' && $r['name'] === $u->name
                && $r['E-mail da conta'] === $u->email && $r['Empresa'] === $t->nome
                && $r['Relato'] === 'O cartão não muda de estágio quando arrasto.'
                && str_starts_with($r['_subject'], '[Suporte #') && ! str_contains($r['_subject'], 'cartão');
        });
    }

    public function test_falha_do_formsubmit_nao_quebra_o_envio_e_o_chamado_fica_gravado(): void
    {
        config(['suporte.canal' => 'formsubmit']);
        Http::fake(['formsubmit.co/*' => Http::response('erro', 500)]);
        $u = $this->agente($this->novoTenant());

        $this->actingAs($u)->postJson(route('api.suporte.store'), $this->dados())->assertCreated();

        $this->assertSame(1, ChamadoDeSuporte::count());
        $this->assertNull(ChamadoDeSuporte::first()->entregue_em);
    }

    public function test_chamado_nao_vaza_entre_empresas(): void
    {
        $a = $this->agente($this->novoTenant());
        $this->actingAs($a)->postJson(route('api.suporte.store'), $this->dados())->assertCreated();

        $this->agente($this->novoTenant());
        $this->assertSame(0, ChamadoDeSuporte::count()); // escopo do tenant ativo
    }
}
