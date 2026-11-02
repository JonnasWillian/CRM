<?php

namespace Tests\Feature\Pagamentos;

use App\Mail\AvisoDeAssinaturaMail;
use App\Models\Assinatura;
use App\Models\EventoDeGateway;
use App\Models\Fatura;
use App\Services\Assinaturas\Assinaturas;
use App\Services\Assinaturas\Faturamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * O aviso do Asaas só ativa o plano depois de três conferências: o token do
 * webhook, a situação da cobrança consultada direto na API (o corpo do aviso
 * não é confiável) e o valor e o cliente batendo com a fatura. O mesmo aviso
 * repetido é processado uma vez.
 */
class WebhookDoAsaasTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private const TOKEN = 'tk_9fQ2xLp7RwZ4nB8sYe3HdJf6UaGc1M0oTi';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.asaas.ambiente' => 'producao',
            'services.asaas.chave' => '$aact_prod_teste',
            'services.asaas.token_webhook' => self::TOKEN,
            'comercial.contato.email' => 'comercial@userflow.test',
        ]);
        Mail::fake();
        Carbon::setTestNow('2026-10-31 10:00:00');
    }

    private function faturaComCobranca(): Fatura
    {
        $tenant = $this->novoTenant();
        $tenant->forceFill(['documento' => '11222333000181', 'asaas_cliente_id' => 'cus_1'])->save();
        $this->agente($tenant, 'admin');
        app(Assinaturas::class)->da($tenant);
        $fatura = app(Faturamento::class)->contratar($tenant, 'profissional', 'mensal');
        $fatura->forceFill(['cobranca_id' => 'pay_1', 'link_pagamento' => 'https://www.asaas.com/i/pay_1'])->save();

        return $fatura;
    }

    /** O que a API do Asaas responde ao consultar a cobrança. */
    private function cobrancaNoAsaas(Fatura $f, string $status = 'RECEIVED', ?float $valor = null, string $cliente = 'cus_1', ?string $referencia = null, string $id = 'pay_1'): void
    {
        Http::fake(["api.asaas.com/v3/payments/{$id}" => Http::response([
            'id' => $id, 'customer' => $cliente, 'status' => $status, 'value' => $valor ?? (float) $f->valor,
            'externalReference' => $referencia ?? "fatura:{$f->id}",
        ])]);
    }

    private function aviso(Fatura $f, string $evento = 'PAYMENT_RECEIVED', string $id = 'evt_1', array $token = ['asaas-access-token' => self::TOKEN])
    {
        return $this->postJson('/api/webhooks/asaas', [
            'id' => $id,
            'event' => $evento,
            'dateCreated' => '2026-10-31 10:00:00',
            'payment' => ['object' => 'payment', 'id' => 'pay_1', 'customer' => 'cus_1', 'value' => (float) $f->valor, 'status' => 'RECEIVED', 'externalReference' => "fatura:{$f->id}"],
        ], $token);
    }

    public function test_sem_token_ou_com_token_errado_e_recusado(): void
    {
        Http::fake();
        $f = $this->faturaComCobranca();

        $this->aviso($f, token: [])->assertStatus(401);
        $this->aviso($f, token: ['asaas-access-token' => 'outro'])->assertStatus(401);

        $this->assertSame('pendente', $f->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_sem_token_configurado_a_rota_nao_responde(): void
    {
        config(['services.asaas.token_webhook' => null]);
        $f = $this->faturaComCobranca();

        $this->aviso($f)->assertNotFound();
    }

    public function test_pix_recebido_e_conferido_na_api_ativa_o_plano(): void
    {
        $f = $this->faturaComCobranca();
        $this->cobrancaNoAsaas($f);

        $this->aviso($f)->assertOk()->assertJsonPath('resultado', 'paga');

        $f->refresh();
        $this->assertSame(['paga', 'asaas', 'pay_1'], [$f->status, $f->gateway, $f->referencia_gateway]);
        $a = Assinatura::where('tenant_id', $f->tenant_id)->first();
        $this->assertSame(['ativa', 'profissional'], [$a->status, $a->plano]);
        Mail::assertQueued(AvisoDeAssinaturaMail::class, fn ($m) => $m->tipo === 'pagamento_confirmado');
    }

    public function test_cartao_confirmado_tambem_ativa(): void
    {
        $f = $this->faturaComCobranca();
        $this->cobrancaNoAsaas($f, 'CONFIRMED');

        $this->aviso($f, 'PAYMENT_CONFIRMED')->assertOk();

        $this->assertSame('paga', $f->fresh()->status);
    }

    public function test_aviso_repetido_e_processado_uma_vez(): void
    {
        $f = $this->faturaComCobranca();
        $this->cobrancaNoAsaas($f);

        $this->aviso($f)->assertOk();
        $this->aviso($f)->assertOk()->assertJsonPath('resultado', 'duplicado');

        Http::assertSentCount(1);
        $this->assertSame(1, EventoDeGateway::count());
    }

    public function test_aviso_sem_pagamento_real_na_api_nao_ativa(): void
    {
        $f = $this->faturaComCobranca();
        $this->cobrancaNoAsaas($f, 'PENDING');

        $this->aviso($f)->assertOk();

        $this->assertSame('pendente', $f->fresh()->status);
        $this->assertStringStartsWith('recusado', EventoDeGateway::sole()->resultado);
    }

    public function test_valor_menor_que_a_fatura_nao_ativa_e_avisa_o_comercial(): void
    {
        $f = $this->faturaComCobranca();
        $this->cobrancaNoAsaas($f, valor: 1.00);

        $this->aviso($f)->assertOk();

        $this->assertSame('pendente', $f->fresh()->status);
        Mail::assertQueued(AvisoDeAssinaturaMail::class, fn ($m) => $m->tipo === 'comercial_alerta' && $m->hasTo('comercial@userflow.test'));
    }

    public function test_cobranca_de_outra_fatura_nao_ativa(): void
    {
        $f = $this->faturaComCobranca();
        $this->cobrancaNoAsaas($f, referencia: 'fatura:999999');

        $this->aviso($f)->assertOk();

        $this->assertSame('pendente', $f->fresh()->status);
    }

    public function test_cliente_recriado_no_asaas_nao_impede_a_confirmacao(): void
    {
        // Dois cliques ao mesmo tempo na primeira cobrança: a empresa ficou com
        // outro cliente do Asaas que não o da cobrança. O pagamento é desta fatura.
        $f = $this->faturaComCobranca();
        $f->tenant->forceFill(['asaas_cliente_id' => 'cus_2'])->save();
        $this->cobrancaNoAsaas($f, cliente: 'cus_1');

        $this->aviso($f)->assertOk()->assertJsonPath('resultado', 'paga');
    }

    public function test_alerta_do_mesmo_pagamento_sai_uma_vez(): void
    {
        $f = $this->faturaComCobranca();
        $this->cobrancaNoAsaas($f, valor: 1.00);

        $this->aviso($f, id: 'evt_1')->assertOk();
        $this->aviso($f, id: 'evt_2')->assertOk();

        $this->assertCount(1, Mail::queued(AvisoDeAssinaturaMail::class, fn ($m) => $m->tipo === 'comercial_alerta'));
    }

    public function test_cobranca_desvinculada_e_paga_e_achada_pela_referencia(): void
    {
        // Removida e restaurada no painel do Asaas: a fatura perdeu o vínculo, o pagamento ainda é dela.
        $f = $this->faturaComCobranca();
        $f->forceFill(['cobranca_id' => null])->save();
        $this->cobrancaNoAsaas($f, id: 'pay_antiga');

        $this->postJson('/api/webhooks/asaas', [
            'id' => 'evt_5', 'event' => 'PAYMENT_RECEIVED',
            'payment' => ['id' => 'pay_antiga', 'externalReference' => "fatura:{$f->id}"],
        ], ['asaas-access-token' => self::TOKEN])->assertOk()->assertJsonPath('resultado', 'paga');

        $this->assertSame(['paga', 'pay_antiga'], [$f->fresh()->status, $f->fresh()->referencia_gateway]);
    }

    public function test_fatura_paga_a_mao_e_paga_de_novo_no_asaas_avisa_o_comercial(): void
    {
        $f = $this->faturaComCobranca();
        $f->forceFill(['status' => 'paga', 'paga_em' => now(), 'gateway' => 'manual', 'referencia_gateway' => 'pix 31/10'])->saveQuietly();
        $this->cobrancaNoAsaas($f);

        $this->aviso($f)->assertOk();

        Mail::assertQueued(AvisoDeAssinaturaMail::class, fn ($m) => $m->tipo === 'comercial_alerta' && str_contains($m->conteudo()['assunto'], 'duas vezes'));
    }

    public function test_erro_de_regra_ao_confirmar_responde_200_para_nao_pausar_a_fila(): void
    {
        $f = $this->faturaComCobranca();
        $this->cobrancaNoAsaas($f);
        $this->mock(Faturamento::class, fn ($m) => $m->shouldReceive('pagar')->andThrow(new \DomainException('A fatura foi cancelada.')));

        $this->aviso($f)->assertOk();

        $this->assertStringStartsWith('erro', EventoDeGateway::sole()->resultado);
    }

    public function test_asaas_fora_do_ar_responde_503_e_o_reenvio_funciona(): void
    {
        $f = $this->faturaComCobranca();
        Http::fakeSequence('api.asaas.com/v3/payments/pay_1')
            ->push('Service Unavailable', 503)
            ->push(['id' => 'pay_1', 'customer' => 'cus_1', 'status' => 'RECEIVED', 'value' => (float) $f->valor, 'externalReference' => "fatura:{$f->id}"]);

        $this->aviso($f)->assertStatus(503);
        $this->assertSame('pendente', $f->fresh()->status);

        $this->aviso($f)->assertOk()->assertJsonPath('resultado', 'paga');
        $this->assertSame('paga', $f->fresh()->status);
    }

    public function test_cobranca_desconhecida_e_ignorada_com_200(): void
    {
        Http::fake();

        $this->postJson('/api/webhooks/asaas', ['id' => 'evt_9', 'event' => 'PAYMENT_RECEIVED', 'payment' => ['id' => 'pay_de_outro_sistema']], ['asaas-access-token' => self::TOKEN])
            ->assertOk();

        Http::assertNothingSent();
        $this->assertStringStartsWith('ignorado', EventoDeGateway::sole()->resultado);
    }

    public function test_estorno_avisa_o_comercial(): void
    {
        $f = $this->faturaComCobranca();
        app(Faturamento::class)->pagar($f, 'pay_1', 'asaas');

        $this->aviso($f, 'PAYMENT_REFUNDED', 'evt_2')->assertOk();

        Mail::assertQueued(AvisoDeAssinaturaMail::class, fn ($m) => $m->tipo === 'comercial_alerta' && str_contains($m->conteudo()['assunto'], 'Estorno'));
    }

    public function test_pagamento_de_fatura_cancelada_nao_ativa_e_avisa_o_comercial(): void
    {
        $f = $this->faturaComCobranca();
        $f->forceFill(['status' => 'cancelada', 'cancelada_em' => now()])->saveQuietly();
        $this->cobrancaNoAsaas($f);

        $this->aviso($f)->assertOk();

        $this->assertSame('cancelada', $f->fresh()->status);
        Mail::assertQueued(AvisoDeAssinaturaMail::class, fn ($m) => $m->tipo === 'comercial_alerta');
    }

    public function test_cobranca_removida_no_painel_libera_a_fatura_para_nova_cobranca(): void
    {
        Http::fake();
        $f = $this->faturaComCobranca();

        $this->aviso($f, 'PAYMENT_DELETED', 'evt_3')->assertOk();

        $f->refresh();
        $this->assertSame(['pendente', null, null], [$f->status, $f->cobranca_id, $f->link_pagamento]);
    }
}
