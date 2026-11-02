<?php

namespace Tests\Feature\Pagamentos;

use App\Models\Fatura;
use App\Services\Assinaturas\Assinaturas;
use App\Services\Assinaturas\Faturamento;
use App\Services\Pagamentos\Asaas\CobrancasDoAsaas;
use App\Services\Pagamentos\Asaas\FalhaNoAsaas;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Cada fatura vira uma cobrança no Asaas, criada uma vez só, com o cliente
 * (a empresa) cadastrado uma vez só e sem os avisos do Asaas: quem avisa é o
 * sistema. Fatura cancelada aqui tem a cobrança removida lá.
 */
class CobrancaNoAsaasTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private const API = 'https://api.asaas.com/v3';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.asaas.ambiente' => 'producao', 'services.asaas.chave' => '$aact_prod_teste']);
        Mail::fake();
        Carbon::setTestNow('2026-10-31 10:00:00');
    }

    private function faturaPendente(?string $documento = '11222333000181'): Fatura
    {
        $tenant = $this->novoTenant();
        $tenant->forceFill(['documento' => $documento])->save();
        $this->agente($tenant, 'admin');
        app(Assinaturas::class)->da($tenant);

        return app(Faturamento::class)->contratar($tenant, 'profissional', 'mensal');
    }

    private function fakeDoAsaas(): void
    {
        Http::fake([
            'api.asaas.com/v3/customers' => Http::response(['id' => 'cus_1']),
            'api.asaas.com/v3/payments' => Http::response(['id' => 'pay_1', 'status' => 'PENDING', 'invoiceUrl' => 'https://www.asaas.com/i/pay_1']),
            'api.asaas.com/v3/payments/pay_1/pixQrCode' => Http::response(['encodedImage' => 'iVBOR', 'payload' => '00020101pix', 'expirationDate' => '2027-11-03 23:59:59']),
            'api.asaas.com/v3/payments/pay_1' => Http::response(['deleted' => true, 'id' => 'pay_1']),
        ]);
    }

    public function test_cria_cliente_e_cobranca_uma_vez_so(): void
    {
        $this->fakeDoAsaas();
        $fatura = $this->faturaPendente();
        $cobrancas = app(CobrancasDoAsaas::class);

        $f = $cobrancas->garantir($fatura, 'financeiro@cliente.com.br');
        $cobrancas->garantir($f->fresh(), 'financeiro@cliente.com.br');

        $this->assertSame(['pay_1', 'https://www.asaas.com/i/pay_1'], [$f->cobranca_id, $f->link_pagamento]);
        $this->assertSame('cus_1', $f->tenant->fresh()->asaas_cliente_id);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $r) => $r->url() === self::API.'/customers'
            && $r->hasHeader('access_token', '$aact_prod_teste') && $r->hasHeader('User-Agent')
            && $r['cpfCnpj'] === '11222333000181' && $r['notificationDisabled'] === true
            && $r['email'] === 'financeiro@cliente.com.br' && $r['externalReference'] === "tenant:{$f->tenant_id}");
        Http::assertSent(fn (Request $r) => $r->url() === self::API.'/payments'
            && $r['customer'] === 'cus_1' && $r['billingType'] === 'UNDEFINED'
            && (float) $r['value'] === (float) $f->valor && $r['dueDate'] === $f->vence_em->toDateString()
            && $r['externalReference'] === "fatura:{$f->id}");
    }

    public function test_sandbox_usa_o_endereco_de_testes(): void
    {
        config(['services.asaas.ambiente' => 'sandbox']);
        Http::fake([
            'api-sandbox.asaas.com/v3/customers' => Http::response(['id' => 'cus_1']),
            'api-sandbox.asaas.com/v3/payments' => Http::response(['id' => 'pay_1', 'invoiceUrl' => 'https://sandbox.asaas.com/i/pay_1']),
        ]);

        app(CobrancasDoAsaas::class)->garantir($this->faturaPendente());

        Http::assertSent(fn (Request $r) => $r->url() === 'https://api-sandbox.asaas.com/v3/payments');
    }

    public function test_sem_cpf_ou_cnpj_pede_o_documento_e_nao_chama_o_asaas(): void
    {
        Http::fake();
        $fatura = $this->faturaPendente(documento: null);

        try {
            app(CobrancasDoAsaas::class)->garantir($fatura);
            $this->fail('Deveria pedir o CPF ou CNPJ.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('documento', $e->errors());
        }
        Http::assertNothingSent();
    }

    public function test_fatura_vencida_e_cobrada_com_vencimento_hoje(): void
    {
        $this->fakeDoAsaas();
        $fatura = $this->faturaPendente();
        $fatura->forceFill(['vence_em' => '2026-10-20'])->save();

        app(CobrancasDoAsaas::class)->garantir($fatura);

        Http::assertSent(fn (Request $r) => $r->url() === self::API.'/payments' && $r['dueDate'] === '2026-10-31');
    }

    public function test_fatura_que_nao_esta_pendente_nao_gera_cobranca(): void
    {
        Http::fake();
        $fatura = $this->faturaPendente();
        $fatura->forceFill(['status' => 'paga'])->save();

        $this->expectException(DomainException::class);
        app(CobrancasDoAsaas::class)->garantir($fatura);
    }

    public function test_erro_de_dados_do_asaas_vira_mensagem_legivel(): void
    {
        Http::fake([
            'api.asaas.com/v3/customers' => Http::response(['id' => 'cus_1']),
            'api.asaas.com/v3/payments' => Http::response(['errors' => [['code' => 'invalid_value', 'description' => 'O valor da cobrança é menor que o mínimo permitido.']]], 400),
        ]);

        try {
            app(CobrancasDoAsaas::class)->garantir($this->faturaPendente());
            $this->fail('Deveria lançar FalhaNoAsaas.');
        } catch (FalhaNoAsaas $e) {
            $this->assertSame('O valor da cobrança é menor que o mínimo permitido.', $e->getMessage());
            $this->assertFalse($e->temporaria);
            $this->assertSame('O valor da cobrança é menor que o mínimo permitido.', $e->paraOCliente());
        }
    }

    public function test_asaas_fora_do_ar_e_falha_temporaria_sem_detalhe_interno(): void
    {
        Http::fake(['api.asaas.com/*' => Http::response('Service Unavailable', 503)]);

        try {
            app(CobrancasDoAsaas::class)->garantir($this->faturaPendente());
            $this->fail('Deveria lançar FalhaNoAsaas.');
        } catch (FalhaNoAsaas $e) {
            $this->assertTrue($e->temporaria);
            $this->assertStringNotContainsString('503', $e->paraOCliente());
        }
    }

    public function test_chave_recusada_nao_mostra_detalhe_de_configuracao_ao_cliente(): void
    {
        Http::fake(['api.asaas.com/*' => Http::response(['errors' => [['code' => 'invalid_access_token', 'description' => 'A chave de API fornecida é inválida']]], 401)]);

        try {
            app(CobrancasDoAsaas::class)->garantir($this->faturaPendente());
            $this->fail('Deveria lançar FalhaNoAsaas.');
        } catch (FalhaNoAsaas $e) {
            $this->assertStringContainsString('ASAAS_API_KEY', $e->getMessage());
            $this->assertStringNotContainsString('ASAAS', $e->paraOCliente());
        }
    }

    public function test_pix_devolve_qr_code_e_copia_e_cola(): void
    {
        $this->fakeDoAsaas();
        $cobrancas = app(CobrancasDoAsaas::class);
        $f = $cobrancas->garantir($this->faturaPendente());

        $this->assertSame(
            ['imagem' => 'data:image/png;base64,iVBOR', 'copia_e_cola' => '00020101pix', 'expira_em' => '2027-11-03 23:59:59'],
            $cobrancas->pix($f),
        );
    }

    public function test_fatura_cancelada_tem_a_cobranca_removida_no_asaas(): void
    {
        $this->fakeDoAsaas();
        $f = app(CobrancasDoAsaas::class)->garantir($this->faturaPendente());

        // Escolher outro plano cancela a fatura pendente.
        app(Faturamento::class)->contratar($f->tenant_id, 'inicial', 'mensal');

        $this->assertSame('cancelada', $f->fresh()->status);
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === self::API.'/payments/pay_1');
    }

    public function test_fatura_confirmada_a_mao_tem_a_cobranca_removida_no_asaas(): void
    {
        $this->fakeDoAsaas();
        $f = app(CobrancasDoAsaas::class)->garantir($this->faturaPendente());

        app(Faturamento::class)->pagar($f, 'transferência 31/10');

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && $r->url() === self::API.'/payments/pay_1');
    }

    public function test_sandbox_em_servidor_de_producao_e_recusado(): void
    {
        Http::fake();
        $this->app->detectEnvironment(fn () => 'production');
        config(['services.asaas.ambiente' => 'sandbox']);

        try {
            app(CobrancasDoAsaas::class)->garantir($this->faturaPendente());
            $this->fail('Deveria recusar o sandbox em produção.');
        } catch (FalhaNoAsaas $e) {
            $this->assertStringContainsString('ASAAS_AMBIENTE', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_sem_chave_a_integracao_fica_desligada_e_nada_e_chamado(): void
    {
        Http::fake();
        config(['services.asaas.chave' => null]);
        $fatura = $this->faturaPendente();
        $fatura->forceFill(['cobranca_id' => 'pay_1'])->save();

        app(Faturamento::class)->contratar($fatura->tenant_id, 'inicial', 'mensal');

        $this->assertFalse(app(CobrancasDoAsaas::class)->ativo());
        Http::assertNothingSent();
    }
}
