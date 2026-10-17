<?php

namespace Tests\Unit\Reports;

use App\Services\Reports\Periodo;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class PeriodoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-17 15:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_presets_produzem_as_janelas_esperadas(): void
    {
        $this->assertSame(['2026-10-10 00:00:00', '2026-10-17 23:59:59'], $this->janela(Periodo::preset('7d')));
        $this->assertSame(['2026-09-17 00:00:00', '2026-10-17 23:59:59'], $this->janela(Periodo::preset('30d')));
        $this->assertSame(['2026-10-01 00:00:00', '2026-10-17 23:59:59'], $this->janela(Periodo::preset('mes_atual')));
        // Mês calendário completo anterior, não 30 dias móveis.
        $this->assertSame(['2026-09-01 00:00:00', '2026-09-30 23:59:59'], $this->janela(Periodo::preset('mes_anterior')));
    }

    public function test_padrao_e_30_dias_e_preset_desconhecido_lanca(): void
    {
        $this->assertSame('30d', Periodo::padrao()->chave());
        $this->expectException(\InvalidArgumentException::class);
        Periodo::preset('ontem');
    }

    public function test_customizado_cobre_os_dias_inteiros(): void
    {
        $p = Periodo::customizado(Carbon::parse('2026-03-05 10:00'), Carbon::parse('2026-03-09 11:00'));
        $this->assertSame(['2026-03-05 00:00:00', '2026-03-09 23:59:59'], $this->janela($p));
        $this->assertSame('custom', $p->chave());
        $this->assertSame('05/03/2026 a 09/03/2026', $p->rotulo());
    }

    public function test_hash_e_canonico_e_independente_da_hora(): void
    {
        $a = Periodo::customizado(Carbon::parse('2026-03-05 10:00'), Carbon::parse('2026-03-09 11:00'));
        $b = Periodo::customizado(Carbon::parse('2026-03-05 23:00'), Carbon::parse('2026-03-09 01:00'));
        $c = Periodo::customizado(Carbon::parse('2026-03-05'), Carbon::parse('2026-03-10'));

        $this->assertSame($a->hash(), $b->hash());
        $this->assertNotSame($a->hash(), $c->hash());
    }

    public function test_de_monta_a_partir_dos_parametros_da_requisicao(): void
    {
        $this->assertSame('mes_anterior', Periodo::de('mes_anterior')->chave());
        $this->assertSame('custom', Periodo::de('custom', '2026-01-01', '2026-01-31')->chave());
        $this->assertSame('30d', Periodo::de(null)->chave());
    }

    private function janela(Periodo $p): array
    {
        return [$p->inicio()->format('Y-m-d H:i:s'), $p->fim()->format('Y-m-d H:i:s')];
    }
}
