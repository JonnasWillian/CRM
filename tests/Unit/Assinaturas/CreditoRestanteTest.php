<?php

namespace Tests\Unit\Assinaturas;

use App\Models\Assinatura;
use App\Services\Assinaturas\Faturamento;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Crédito do que sobra do ciclo pago, usado numa troca para cima. Sem banco. */
class CreditoRestanteTest extends TestCase
{
    private function credito(array $attrs): float
    {
        return app(Faturamento::class)->creditoRestante(new Assinatura($attrs + ['status' => 'ativa', 'plano' => 'inicial', 'ciclo' => 'anual']));
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-29 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_proporcional_ao_tempo_que_falta(): void
    {
        // 365 dias de ciclo, 182 restantes.
        $this->assertSame(round(468 * 182 / 365, 2), $this->credito(['inicio_do_ciclo' => '2026-04-29 10:00:00', 'vigente_ate' => '2027-04-29 10:00:00', 'valor_do_ciclo' => 468]));
    }

    public function test_ciclo_que_ainda_nao_comecou_vale_inteiro(): void
    {
        // Pago durante o teste: o ciclo começa no fim do teste.
        $this->assertSame(828.0, $this->credito(['inicio_do_ciclo' => '2026-11-05 10:00:00', 'vigente_ate' => '2027-11-05 10:00:00', 'valor_do_ciclo' => 828]));
    }

    public function test_sem_dados_do_ciclo_ou_fora_de_ativa_nao_ha_credito(): void
    {
        $this->assertSame(0.0, $this->credito(['inicio_do_ciclo' => null, 'vigente_ate' => '2027-04-29', 'valor_do_ciclo' => null]));
        $this->assertSame(0.0, $this->credito(['inicio_do_ciclo' => '2025-10-27', 'vigente_ate' => '2026-10-27', 'valor_do_ciclo' => 468])); // carência
        $this->assertSame(0.0, $this->credito(['status' => 'cancelada', 'inicio_do_ciclo' => '2026-04-30', 'vigente_ate' => '2027-04-29', 'valor_do_ciclo' => 468]));
    }
}
