<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Telefone em E.164: "+", código do país sem zero, 10 a 15 dígitos no total. */
class TelefoneE164 implements ValidationRule
{
    private const PADRAO = '/^\+[1-9]\d{9,14}$/';

    public static function valido(?string $valor): bool
    {
        return $valor !== null && preg_match(self::PADRAO, $valor) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::valido($value)) {
            $fail('Informe um telefone válido com DDD, por exemplo (11) 99999-8888.');
        }
    }
}
