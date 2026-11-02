<?php

namespace Tests\Unit\Rules;

use App\Rules\CpfOuCnpj;
use PHPUnit\Framework\TestCase;

/** O Asaas exige CPF ou CNPJ de quem paga: os dígitos verificadores são conferidos antes de chamar a API. */
class CpfOuCnpjTest extends TestCase
{
    public function test_aceita_cpf_e_cnpj_validos_com_ou_sem_pontuacao(): void
    {
        foreach (['529.982.247-25', '52998224725', '11.222.333/0001-81', '11222333000181'] as $documento) {
            $this->assertTrue(CpfOuCnpj::valido($documento), $documento);
        }
    }

    public function test_recusa_digito_errado_sequencia_repetida_e_tamanho_errado(): void
    {
        foreach (['529.982.247-24', '111.111.111-11', '11.222.333/0001-80', '00000000000000', '1234', ''] as $documento) {
            $this->assertFalse(CpfOuCnpj::valido($documento), $documento);
        }
    }

    public function test_digitos_tira_a_pontuacao(): void
    {
        $this->assertSame('11222333000181', CpfOuCnpj::digitos('11.222.333/0001-81'));
    }

    /** CNPJ alfanumérico (IN RFB 2.229/2024), emitido desde julho de 2026: letras nas 12 primeiras posições. */
    public function test_aceita_cnpj_alfanumerico_e_guarda_em_maiusculas(): void
    {
        $this->assertTrue(CpfOuCnpj::valido('12.ABC.345/01DE-35'));
        $this->assertTrue(CpfOuCnpj::valido('12.abc.345/01de-35'));
        $this->assertFalse(CpfOuCnpj::valido('12.ABC.345/01DE-36'));
        $this->assertFalse(CpfOuCnpj::valido('12.ABC.345/01DE-3E')); // dígito verificador é sempre número
        $this->assertFalse(CpfOuCnpj::valido('529.982.24A-25')); // CPF continua só com números
        $this->assertSame('12ABC34501DE35', CpfOuCnpj::digitos('12.abc.345/01de-35'));
    }
}
