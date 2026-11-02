<?php

namespace Tests\Feature\Pagamentos;

use App\Models\Fatura;
use App\Services\Assinaturas\Assinaturas;
use App\Services\Assinaturas\Faturamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * asaas:verificar confere a conexão; asaas:webhook cadastra o aviso de
 * pagamento pela API; asaas:conciliar confirma o que o webhook perdeu
 * (servidor fora do ar, fila pausada, ambiente local sem endereço público).
 */
class ComandosDoAsaasTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private const TOKEN = 'tk_9fQ2xLp7RwZ4nB8sYe3HdJf6UaGc1M0oTi';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.asaas.ambiente' => 'producao', 'services.asaas.chave' => '$aact_prod_segredo', 'services.asaas.token_webhook' => self::TOKEN]);
        Mail::fake();
        Carbon::setTestNow('2026-10-31 10:00:00');
    }

    public function test_verificar_conecta_sem_mostrar_a_chave(): void
    {
        Http::fake(['api.asaas.com/v3/customers*' => Http::response(['data' => [], 'totalCount' => 0])]);

        $codigo = Artisan::call('asaas:verificar');
        $saida = Artisan::output();

        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('produção', $saida);
        $this->assertStringNotContainsString('segredo', $saida);
    }

    public function test_verificar_sem_chave_explica_o_que_falta(): void
    {
        config(['services.asaas.chave' => null]);

        $this->artisan('asaas:verificar')->expectsOutputToContain('ASAAS_API_KEY')->assertExitCode(1);
    }

    public function test_webhook_e_cadastrado_com_token_eventos_e_ordem(): void
    {
        Http::fake(['api.asaas.com/v3/webhooks' => Http::response(['id' => 'wh_1'])]);

        $this->artisan('asaas:webhook', ['--url' => 'https://crm.exemplo.com.br/api/webhooks/asaas'])->assertExitCode(0);

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->url() === 'https://api.asaas.com/v3/webhooks'
            && $r['url'] === 'https://crm.exemplo.com.br/api/webhooks/asaas'
            && $r['authToken'] === self::TOKEN && $r['enabled'] === true && $r['sendType'] === 'SEQUENTIALLY'
            && empty(array_diff(['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_REFUNDED', 'PAYMENT_CHARGEBACK_REQUESTED', 'PAYMENT_DELETED'], $r['events'])));
    }

    public function test_webhook_exige_token_de_32_caracteres_e_endereco_https(): void
    {
        Http::fake();

        config(['services.asaas.token_webhook' => 'curto']);
        $this->artisan('asaas:webhook', ['--url' => 'https://crm.exemplo.com.br/api/webhooks/asaas'])->expectsOutputToContain('ASAAS_WEBHOOK_TOKEN')->assertExitCode(1);

        config(['services.asaas.token_webhook' => self::TOKEN]);
        $this->artisan('asaas:webhook', ['--url' => 'http://127.0.0.1:8000/api/webhooks/asaas'])->expectsOutputToContain('https')->assertExitCode(1);

        Http::assertNothingSent();
    }

    private function faturaComCobranca(string $cobranca): Fatura
    {
        $tenant = $this->novoTenant();
        $tenant->forceFill(['documento' => '11222333000181', 'asaas_cliente_id' => "cus_{$cobranca}"])->save();
        $this->agente($tenant, 'admin');
        app(Assinaturas::class)->da($tenant);
        $fatura = app(Faturamento::class)->contratar($tenant, 'profissional', 'mensal');
        $fatura->forceFill(['cobranca_id' => $cobranca])->save();

        return $fatura;
    }

    public function test_conciliar_confirma_o_que_foi_pago_e_deixa_o_resto(): void
    {
        $paga = $this->faturaComCobranca('pay_a');
        $aberta = $this->faturaComCobranca('pay_b');
        Http::fake([
            'api.asaas.com/v3/payments/pay_a' => Http::response(['id' => 'pay_a', 'customer' => 'cus_pay_a', 'status' => 'RECEIVED', 'value' => (float) $paga->valor, 'externalReference' => "fatura:{$paga->id}"]),
            'api.asaas.com/v3/payments/pay_b' => Http::response(['id' => 'pay_b', 'customer' => 'cus_pay_b', 'status' => 'PENDING', 'value' => (float) $aberta->valor, 'externalReference' => "fatura:{$aberta->id}"]),
        ]);

        $codigo = Artisan::call('asaas:conciliar');

        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('1 fatura confirmada', Artisan::output());
        $this->assertSame(['paga', 'pendente'], [$paga->fresh()->status, $aberta->fresh()->status]);
    }

    public function test_conciliar_segue_quando_uma_fatura_da_erro(): void
    {
        $this->faturaComCobranca('pay_a');
        $paga = $this->faturaComCobranca('pay_b');
        Http::fake([
            'api.asaas.com/v3/payments/pay_a' => fn () => throw new \RuntimeException('resposta inesperada'),
            'api.asaas.com/v3/payments/pay_b' => Http::response(['id' => 'pay_b', 'customer' => 'cus_pay_b', 'status' => 'RECEIVED', 'value' => (float) $paga->valor, 'externalReference' => "fatura:{$paga->id}"]),
        ]);

        $codigo = Artisan::call('asaas:conciliar');

        $this->assertSame(0, $codigo);
        $this->assertSame('paga', $paga->fresh()->status);
        $this->assertStringContainsString('1 não pôde', Artisan::output());
    }

    public function test_conciliar_confere_canceladas_recentes_e_avisa_se_foram_pagas(): void
    {
        config(['comercial.contato.email' => 'comercial@userflow.test']);
        $recente = $this->faturaComCobranca('pay_a');
        $recente->forceFill(['status' => 'cancelada', 'cancelada_em' => now()->subDays(2)])->saveQuietly();
        $antiga = $this->faturaComCobranca('pay_b');
        $antiga->forceFill(['status' => 'cancelada', 'cancelada_em' => now()->subDays(20)])->saveQuietly();
        Http::fake(['api.asaas.com/v3/payments/pay_a' => Http::response(['id' => 'pay_a', 'status' => 'RECEIVED', 'value' => (float) $recente->valor, 'externalReference' => "fatura:{$recente->id}"])]);

        Artisan::call('asaas:conciliar');

        Mail::assertQueued(\App\Mail\AvisoDeAssinaturaMail::class, fn ($m) => $m->tipo === 'comercial_alerta');
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'pay_b'));
        $this->assertSame('cancelada', $recente->fresh()->status);
    }

    public function test_conciliar_sem_integracao_nao_faz_nada(): void
    {
        Http::fake();
        config(['services.asaas.chave' => null]);

        $this->artisan('asaas:conciliar')->assertExitCode(0);

        Http::assertNothingSent();
    }
}
