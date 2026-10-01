<?php

namespace Tests\Unit;

use App\Rules\TelefoneE164;
use App\Support\Telefone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TelefoneTest extends TestCase
{
    public static function entradas(): array
    {
        return [
            'celular com máscara' => ['(11) 99999-8888', '+5511999998888'],
            'celular só dígitos' => ['11999998888', '+5511999998888'],
            'fixo com máscara' => ['(11) 3333-4444', '+551133334444'],
            'com zero de tronco' => ['011999998888', '+5511999998888'],
            'com 55 sem mais' => ['5511999998888', '+5511999998888'],
            // DDD 55 é do Rio Grande do Sul e coincide com o DDI do Brasil:
            // com 11 dígitos (sem "+") o número é tratado como DDD + linha,
            // nunca como DDI + número de 9 dígitos (que não existe no Brasil).
            'DDD 55 (RS) colide com DDI' => ['55 99999-8888', '+5555999998888'],
            'já em E.164 com espaços' => ['+55 11 99999-8888', '+5511999998888'],
            'internacional' => ['+1 (415) 555-2671', '+14155552671'],
            'curto demais' => ['123', '123'],
            'sem dígitos' => ['abc', 'abc'],
            'vazio' => ['', null],
            'só espaços' => ['   ', null],
            'nulo' => [null, null],
        ];
    }

    #[DataProvider('entradas')]
    public function test_normalizar(?string $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, Telefone::normalizar($entrada));
    }

    public function test_a_regra_aceita_so_e164(): void
    {
        $this->assertTrue(TelefoneE164::valido('+5511999998888'));
        $this->assertTrue(TelefoneE164::valido('+14155552671'));
        $this->assertFalse(TelefoneE164::valido('123'));
        $this->assertFalse(TelefoneE164::valido('11999998888'));
        $this->assertFalse(TelefoneE164::valido('+0011999998888'));
        $this->assertFalse(TelefoneE164::valido(null));
    }

    public function test_formatar(): void
    {
        $this->assertSame('(11) 99999-8888', Telefone::formatar('+5511999998888'));
        $this->assertSame('(11) 3333-4444', Telefone::formatar('+551133334444'));
        $this->assertSame('+14155552671', Telefone::formatar('+14155552671'));
        $this->assertSame('', Telefone::formatar(null));
    }
}
