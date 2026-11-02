<?php

namespace Tests\Feature\Pagamentos;

use App\Models\Assinatura;
use App\Models\Fatura;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Assinaturas\Assinaturas;
use App\Services\Assinaturas\Faturamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Configurações › Assinatura: o admin paga a fatura pendente por Pix na
 * própria tela (ou abre a página do Asaas para boleto e cartão), e a tela
 * descobre sozinha quando o pagamento cai.
 */
class PagarFaturaPelaTelaTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.asaas.ambiente' => 'producao', 'services.asaas.chave' => '$aact_prod_teste', 'comercial.exigir_assinatura' => true]);
        Mail::fake();
        Carbon::setTestNow('2026-10-31 10:00:00');
        $this->tenant = $this->novoTenant();
        $this->admin = $this->agente($this->tenant, 'admin');
        app(Assinaturas::class)->da($this->tenant);
    }

    private function fatura(): Fatura
    {
        return app(Faturamento::class)->contratar($this->tenant, 'profissional', 'mensal');
    }

    private function fakeDoAsaas(): void
    {
        Http::fake([
            'api.asaas.com/v3/customers' => Http::response(['id' => 'cus_1']),
            'api.asaas.com/v3/payments' => Http::response(['id' => 'pay_1', 'status' => 'PENDING', 'invoiceUrl' => 'https://www.asaas.com/i/pay_1']),
            'api.asaas.com/v3/payments/pay_1/pixQrCode' => Http::response(['encodedImage' => 'iVBOR', 'payload' => '00020101pix', 'expirationDate' => '2027-11-03 23:59:59']),
        ]);
    }

    public function test_admin_informa_o_cnpj_e_recebe_o_pix_e_o_link(): void
    {
        $this->fakeDoAsaas();
        $f = $this->fatura();

        $this->actingAs($this->admin)
            ->postJson(route('configuracoes.assinatura.pagar', $f), ['documento' => '11.222.333/0001-81'])
            ->assertOk()
            ->assertJsonPath('link', 'https://www.asaas.com/i/pay_1')
            ->assertJsonPath('pix.copia_e_cola', '00020101pix')
            ->assertJsonPath('pix.imagem', 'data:image/png;base64,iVBOR');

        $this->assertSame('11222333000181', $this->tenant->fresh()->documento);
    }

    public function test_cpf_ou_cnpj_invalido_volta_erro_no_campo_sem_chamar_o_asaas(): void
    {
        Http::fake();
        $f = $this->fatura();

        $this->actingAs($this->admin)
            ->postJson(route('configuracoes.assinatura.pagar', $f), ['documento' => '11.222.333/0001-80'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('documento');

        Http::assertNothingSent();
    }

    public function test_sem_documento_salvo_o_campo_e_obrigatorio(): void
    {
        Http::fake();
        $f = $this->fatura();

        $this->actingAs($this->admin)->postJson(route('configuracoes.assinatura.pagar', $f), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['documento' => 'Informe o CPF ou CNPJ']);
    }

    public function test_com_documento_salvo_nao_precisa_informar_de_novo(): void
    {
        $this->fakeDoAsaas();
        $this->tenant->forceFill(['documento' => '52998224725'])->save();

        $this->actingAs($this->admin)->postJson(route('configuracoes.assinatura.pagar', $this->fatura()), [])->assertOk();
    }

    public function test_fatura_de_outra_empresa_responde_404(): void
    {
        Http::fake();
        $f = $this->fatura();
        $outra = $this->novoTenant();
        $adminDaOutra = $this->agente($outra, 'admin');

        $this->actingAs($adminDaOutra)->postJson(route('configuracoes.assinatura.pagar', $f), ['documento' => '52998224725'])->assertNotFound();
        $this->actingAs($adminDaOutra)->getJson(route('configuracoes.assinatura.fatura', $f))->assertNotFound();
    }

    public function test_vendedor_nao_paga_fatura(): void
    {
        Http::fake();
        $vendedor = $this->agente($this->tenant);

        $this->actingAs($vendedor)->postJson(route('configuracoes.assinatura.pagar', $this->fatura()), ['documento' => '52998224725'])->assertForbidden();
    }

    public function test_sem_integracao_configurada_a_rota_responde_404(): void
    {
        Http::fake();
        config(['services.asaas.chave' => null]);

        $this->actingAs($this->admin)->postJson(route('configuracoes.assinatura.pagar', $this->fatura()), ['documento' => '52998224725'])->assertNotFound();
    }

    public function test_falha_do_asaas_vira_mensagem_para_tentar_de_novo(): void
    {
        Http::fake(['api.asaas.com/*' => Http::response('', 503)]);

        $this->actingAs($this->admin)->postJson(route('configuracoes.assinatura.pagar', $this->fatura()), ['documento' => '52998224725'])
            ->assertStatus(503)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Tente de novo'));
    }

    public function test_empresa_com_acesso_suspenso_consegue_pagar(): void
    {
        $this->fakeDoAsaas();
        Assinatura::where('tenant_id', $this->tenant->id)->update(['teste_termina_em' => now()->subDays(45)]);
        $this->assertTrue(app(Assinaturas::class)->da($this->tenant)->suspensa());

        $this->actingAs($this->admin)->postJson(route('configuracoes.assinatura.pagar', $this->fatura()), ['documento' => '52998224725'])->assertOk();
    }

    public function test_situacao_confere_no_asaas_no_maximo_a_cada_15_segundos(): void
    {
        $f = $this->fatura();
        $this->tenant->forceFill(['asaas_cliente_id' => 'cus_1'])->save();
        $f->forceFill(['cobranca_id' => 'pay_1'])->save();
        Http::fakeSequence('api.asaas.com/v3/payments/pay_1')
            ->push(['id' => 'pay_1', 'customer' => 'cus_1', 'status' => 'PENDING', 'value' => (float) $f->valor, 'externalReference' => "fatura:{$f->id}"])
            ->push(['id' => 'pay_1', 'customer' => 'cus_1', 'status' => 'RECEIVED', 'value' => (float) $f->valor, 'externalReference' => "fatura:{$f->id}"]);

        $this->actingAs($this->admin)->getJson(route('configuracoes.assinatura.fatura', $f))->assertOk()->assertJsonPath('status', 'pendente');
        $this->actingAs($this->admin)->getJson(route('configuracoes.assinatura.fatura', $f))->assertOk()->assertJsonPath('status', 'pendente');
        Http::assertSentCount(1);

        Carbon::setTestNow(now()->addSeconds(16));
        $this->actingAs($this->admin)->getJson(route('configuracoes.assinatura.fatura', $f))->assertOk()->assertJsonPath('status', 'paga');
        $this->assertSame('ativa', Assinatura::where('tenant_id', $this->tenant->id)->value('status'));
    }

    public function test_situacao_diz_quando_o_asaas_foi_conferido_pela_ultima_vez(): void
    {
        $f = $this->fatura();
        $this->actingAs($this->admin)->getJson(route('configuracoes.assinatura.fatura', $f))->assertOk()->assertJsonPath('conferido_em', null);

        $f->forceFill(['cobranca_id' => 'pay_1'])->save();
        Http::fakeSequence('api.asaas.com/v3/payments/pay_1')
            ->push(['id' => 'pay_1', 'status' => 'PENDING', 'value' => (float) $f->valor, 'externalReference' => "fatura:{$f->id}"])
            ->push([], 500);
        $primeira = now()->toIso8601String();

        $this->actingAs($this->admin)->getJson(route('configuracoes.assinatura.fatura', $f))->assertOk()->assertJsonPath('conferido_em', $primeira);
        Carbon::setTestNow(now()->addSeconds(5)); // dentro dos 15 s: não consulta, repete a última
        $this->actingAs($this->admin)->getJson(route('configuracoes.assinatura.fatura', $f))->assertJsonPath('conferido_em', $primeira);
        Carbon::setTestNow(now()->addSeconds(16)); // o Asaas falhou: a última conferência continua a anterior
        $this->actingAs($this->admin)->getJson(route('configuracoes.assinatura.fatura', $f))->assertOk()->assertJsonPath('conferido_em', $primeira);
    }

    public function test_tela_diz_se_o_pagamento_online_esta_ligado_e_se_falta_o_documento(): void
    {
        $this->fatura();

        $this->actingAs($this->admin)->get(route('configuracoes.assinatura'))
            ->assertInertia(fn (Assert $p) => $p
                ->where('pagamento.online', true)
                ->where('pagamento.documento', false));
    }
}
