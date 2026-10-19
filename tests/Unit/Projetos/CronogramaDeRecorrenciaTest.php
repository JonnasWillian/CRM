<?php

namespace Tests\Unit\Projetos;

use App\Support\Projetos\CronogramaDeRecorrencia;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Contrato recorrente: períodos em sequência (contrato inicial + renovações
 * e aditivos), cada um com N meses a um valor mensal. O mês conta a partir
 * do dia de aniversário do início, e o mês corrente já conta.
 */
class CronogramaDeRecorrenciaTest extends TestCase
{
    private function cronograma(array $periodos, string $hoje, ?string $cancelado = null, string $inicio = '2026-08-15'): CronogramaDeRecorrencia
    {
        return new CronogramaDeRecorrencia(Carbon::parse($inicio), $periodos, Carbon::parse($hoje), $cancelado ? Carbon::parse($cancelado) : null);
    }

    public function test_vigente_conta_os_meses_desde_o_inicio_incluindo_o_atual(): void
    {
        $c = $this->cronograma([['meses' => 12, 'valor_mensal' => 1000]], '2026-10-19');

        $this->assertSame('vigente', $c->situacao());
        $this->assertSame(12, $c->mesesTotal());
        $this->assertSame(3, $c->mesesDecorridos()); // 15/08, 15/09, 15/10
        $this->assertEquals(12000, $c->valorTotal());
        $this->assertEquals(3000, $c->valorRealizado());
        $this->assertEquals(9000, $c->valorARealizar());
        $this->assertEquals(1000, $c->valorMesAtual());
        $this->assertSame('2027-08-14', $c->fim()->toDateString());
    }

    public function test_antes_do_aniversario_o_mes_ainda_nao_conta(): void
    {
        $c = $this->cronograma([['meses' => 12, 'valor_mensal' => 1000]], '2026-10-10');

        $this->assertSame(2, $c->mesesDecorridos());
        $this->assertEquals(2000, $c->valorRealizado());
    }

    public function test_a_iniciar_e_encerrado(): void
    {
        $antes = $this->cronograma([['meses' => 6, 'valor_mensal' => 500]], '2026-08-01');
        $this->assertSame('a_iniciar', $antes->situacao());
        $this->assertSame(0, $antes->mesesDecorridos());
        $this->assertEquals(0, $antes->valorMesAtual());

        $depois = $this->cronograma([['meses' => 6, 'valor_mensal' => 500]], '2027-03-01');
        $this->assertSame('encerrado', $depois->situacao());
        $this->assertSame(6, $depois->mesesDecorridos());
        $this->assertEquals(3000, $depois->valorRealizado());
        $this->assertEquals(0, $depois->valorARealizar());
        $this->assertEquals(0, $depois->valorMesAtual());
    }

    public function test_renovacao_com_novo_valor_continua_depois_do_fim(): void
    {
        $periodos = [
            ['meses' => 3, 'valor_mensal' => 1000],
            ['meses' => 6, 'valor_mensal' => 1200], // renovação com reajuste
            ['meses' => 2, 'valor_mensal' => null], // aditivo sem mudar valor: segue 1.200
        ];

        $c = $this->cronograma($periodos, '2027-01-20'); // 6º mês (15/01)
        $this->assertSame(11, $c->mesesTotal());
        $this->assertEquals(3000 + 7200 + 2400, $c->valorTotal());
        $this->assertSame(6, $c->mesesDecorridos());
        $this->assertEquals(3000 + 3 * 1200, $c->valorRealizado());
        $this->assertEquals(1200, $c->valorMesAtual());
        $this->assertSame('2027-07-14', $c->fim()->toDateString());

        $p = $c->periodos();
        $this->assertSame('2026-11-15', $p[1]['inicio']);
        $this->assertSame('2027-05-14', $p[1]['fim']);
        $this->assertEquals(1200, $p[2]['valor_mensal']);
    }

    public function test_cancelado_para_de_contar_no_cancelamento(): void
    {
        $c = $this->cronograma([['meses' => 12, 'valor_mensal' => 1000]], '2027-02-01', '2026-11-20');

        $this->assertSame('cancelado', $c->situacao());
        $this->assertSame(4, $c->mesesDecorridos()); // ago, set, out, nov
        $this->assertEquals(4000, $c->valorRealizado());
        $this->assertEquals(0, $c->valorARealizar());
        $this->assertEquals(0, $c->valorMesAtual());
    }

    public function test_vence_em_breve_no_ultimo_mes(): void
    {
        $this->assertTrue($this->cronograma([['meses' => 3, 'valor_mensal' => 100]], '2026-10-20')->venceEmBreve());
        $this->assertFalse($this->cronograma([['meses' => 12, 'valor_mensal' => 100]], '2026-10-20')->venceEmBreve());
    }

    public function test_inicio_no_dia_31_nao_pula_meses_curtos(): void
    {
        $c = $this->cronograma([['meses' => 3, 'valor_mensal' => 100]], '2027-02-28', null, '2027-01-31');

        $this->assertSame(2, $c->mesesDecorridos()); // 31/01 e 28/02
        $this->assertSame('2027-04-29', $c->fim()->toDateString());
    }
}
