<?php

namespace Tests\Unit\Assinaturas;

use App\Models\Assinatura;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Situação calculada na hora, sem cron. Os limites exatos de cada prazo:
 * no instante do fim, já virou (isFuture é estrito). Sem banco.
 */
class SituacaoDaAssinaturaTest extends TestCase
{
    private function assinatura(array $attrs): Assinatura
    {
        return new Assinatura($attrs + ['plano' => 'profissional', 'ciclo' => 'mensal']);
    }

    private function em(string $instante, Assinatura $a): Assinatura
    {
        Carbon::setTestNow($instante);

        return $a;
    }

    public function test_teste_vale_ate_o_instante_do_fim(): void
    {
        $a = $this->assinatura(['status' => 'teste', 'teste_termina_em' => '2026-11-05 10:00:00']);

        $this->assertSame('teste', $this->em('2026-11-05 09:59:59', $a)->situacao());
        $this->assertSame('teste_expirado', $this->em('2026-11-05 10:00:00', $a)->situacao());
    }

    public function test_plano_pago_passa_por_ativa_carencia_e_vencida(): void
    {
        $a = $this->assinatura(['status' => 'ativa', 'vigente_ate' => '2026-11-05 10:00:00']);

        $this->assertSame('ativa', $this->em('2026-11-05 09:59:59', $a)->situacao());
        $this->assertSame('carencia', $this->em('2026-11-05 10:00:00', $a)->situacao());
        $this->assertSame('carencia', $this->em('2026-11-08 09:59:59', $a)->situacao());
        $this->assertSame('vencida', $this->em('2026-11-08 10:00:00', $a)->situacao());
    }

    public function test_carencia_zero_vence_no_instante_do_fim(): void
    {
        config(['comercial.carencia_dias' => 0]);
        $a = $this->assinatura(['status' => 'ativa', 'vigente_ate' => '2026-11-05 10:00:00']);

        $this->assertSame('vencida', $this->em('2026-11-05 10:00:00', $a)->situacao());
    }

    public function test_renovacao_cancelada_termina_sem_carencia(): void
    {
        $a = $this->assinatura(['status' => 'ativa', 'vigente_ate' => '2026-11-05 10:00:00', 'renovacao_cancelada_em' => '2026-10-20 10:00:00']);

        $this->assertSame('ativa', $this->em('2026-11-05 09:59:59', $a)->situacao());
        $this->assertSame('cancelada', $this->em('2026-11-05 10:00:00', $a)->situacao());
        $this->assertSame('2026-11-05 10:00:00', $a->somenteLeituraDesde()->toDateTimeString());
    }

    public function test_so_grava_quem_esta_em_teste_ativa_ou_carencia(): void
    {
        Carbon::setTestNow('2026-10-29 10:00:00');
        $casos = [
            'teste' => ['status' => 'teste', 'teste_termina_em' => '2026-11-01'],
            'teste_expirado' => ['status' => 'teste', 'teste_termina_em' => '2026-10-01'],
            'ativa' => ['status' => 'ativa', 'vigente_ate' => '2026-11-29'],
            'carencia' => ['status' => 'ativa', 'vigente_ate' => '2026-10-28'],
            'vencida' => ['status' => 'ativa', 'vigente_ate' => '2026-10-01'],
            'cancelada' => ['status' => 'cancelada', 'vigente_ate' => '2026-11-29', 'cancelada_em' => '2026-10-20'],
        ];

        foreach ($casos as $esperado => $attrs) {
            $a = $this->assinatura($attrs);
            $this->assertSame($esperado, $a->situacao());
            $this->assertSame(in_array($esperado, ['teste_expirado', 'vencida', 'cancelada'], true), $a->bloqueada(), $esperado);
        }
    }

    public function test_so_leitura_comeca_no_motivo_certo_e_suspende_trinta_dias_depois(): void
    {
        Carbon::setTestNow('2026-10-29 10:00:00');

        $teste = $this->assinatura(['status' => 'teste', 'teste_termina_em' => '2026-10-20 10:00:00']);
        $this->assertSame('2026-10-20 10:00:00', $teste->somenteLeituraDesde()->toDateTimeString());
        $this->assertSame('2026-11-19 10:00:00', $teste->suspendeEm()->toDateTimeString());

        $vencida = $this->assinatura(['status' => 'ativa', 'vigente_ate' => '2026-10-20 10:00:00']);
        $this->assertSame('2026-10-23 10:00:00', $vencida->somenteLeituraDesde()->toDateTimeString()); // depois da carência

        $cancelada = $this->assinatura(['status' => 'cancelada', 'vigente_ate' => '2026-12-01', 'cancelada_em' => '2026-10-25 08:00:00']);
        $this->assertSame('2026-10-25 08:00:00', $cancelada->somenteLeituraDesde()->toDateTimeString());

        $emDia = $this->assinatura(['status' => 'ativa', 'vigente_ate' => '2026-12-01']);
        $this->assertNull($emDia->somenteLeituraDesde());
        $this->assertNull($emDia->suspendeEm());
        $this->assertFalse($emDia->suspensa());
    }

    public function test_suspensao_vale_a_partir_do_instante_seguinte_ao_prazo_e_segue_a_configuracao(): void
    {
        $a = $this->assinatura(['status' => 'teste', 'teste_termina_em' => '2026-10-01 10:00:00']);

        $this->assertFalse($this->em('2026-10-31 09:59:59', $a)->suspensa());
        $this->assertTrue($this->em('2026-10-31 10:00:01', $a)->suspensa());

        config(['comercial.suspender_apos_dias' => 60]);
        $this->assertFalse($a->suspensa());
    }

    public function test_dias_restantes_conta_dias_de_calendario(): void
    {
        Carbon::setTestNow('2026-10-29 10:00:00');

        $this->assertSame(1, $this->assinatura(['status' => 'teste', 'teste_termina_em' => '2026-10-30 09:00:00'])->diasRestantes());
        $this->assertSame(0, $this->assinatura(['status' => 'teste', 'teste_termina_em' => '2026-10-29 23:00:00'])->diasRestantes());
        $this->assertSame(31, $this->assinatura(['status' => 'ativa', 'vigente_ate' => '2026-11-29 10:00:00'])->diasRestantes());
        $this->assertSame(2, $this->assinatura(['status' => 'ativa', 'vigente_ate' => '2026-10-28 10:00:00'])->diasRestantes()); // carência até 31/10
        $this->assertSame(0, $this->assinatura(['status' => 'ativa', 'vigente_ate' => '2026-10-01'])->diasRestantes());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
