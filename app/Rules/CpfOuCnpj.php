<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CPF (11 números) ou CNPJ (14 caracteres), com ou sem pontuação, com os
 * dígitos verificadores conferidos. O CNPJ pode ser alfanumérico (IN RFB
 * 2.229/2024, emitido desde julho de 2026): letras nas 12 primeiras
 * posições, cada caractere vale o código ASCII − 48, e os dois dígitos
 * verificadores continuam números.
 */
class CpfOuCnpj implements ValidationRule
{
    /** Sem pontuação e em maiúsculas: o formato guardado e enviado ao Asaas. */
    public static function digitos(?string $valor): string
    {
        return preg_replace('/[^0-9A-Z]/', '', strtoupper((string) $valor));
    }

    public static function valido(?string $valor): bool
    {
        $d = self::digitos($valor);
        if (preg_match('/^(\d)\1+$/', $d) === 1) {
            return false;
        }

        if (preg_match('/^\d{11}$/', $d) === 1) {
            return self::confere($d, 9, range(10, 2)) && self::confere($d, 10, range(11, 2));
        }

        return preg_match('/^[0-9A-Z]{12}\d{2}$/', $d) === 1
            && self::confere($d, 12, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2])
            && self::confere($d, 13, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
    }

    /** Dígito na posição `$posicao` = 11 − (Σ valor × peso mod 11), ou 0 quando o resto é menor que 2. */
    private static function confere(string $d, int $posicao, array $pesos): bool
    {
        $soma = 0;
        foreach ($pesos as $i => $peso) {
            $soma += (ord($d[$i]) - 48) * $peso;
        }
        $resto = $soma % 11;

        return (int) $d[$posicao] === ($resto < 2 ? 0 : 11 - $resto);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::valido($value)) {
            $fail('CPF ou CNPJ inválido. Confira os números.');
        }
    }
}
