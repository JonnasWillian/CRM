<?php

namespace App\Support;

/**
 * Telefone é texto, não número: tem "+" de país e pode começar com zero.
 *
 * Forma canônica é E.164 (`+5511999998888`), a mesma que WhatsApp e
 * operadoras usam. Sem código de país, assume Brasil — é o público do CRM.
 *
 * `normalizar` não valida: o que ela não reconhece volta como veio (limpo,
 * sem "+"), e quem recusa é a regra TelefoneE164. Assim a mensagem de erro
 * fala do número que o usuário digitou, e nada é descartado em silêncio.
 */
final class Telefone
{
    public static function normalizar(?string $valor): ?string
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $texto);

        if ($digitos === '') {
            return $texto;
        }

        if (str_starts_with($texto, '+')) {
            return '+'.$digitos;
        }

        // Zero de tronco ("0 11 …") não faz parte do número.
        $digitos = ltrim($digitos, '0');
        $tamanho = strlen($digitos);

        if ($tamanho === 10 || $tamanho === 11) {
            return '+55'.$digitos;
        }

        if (($tamanho === 12 || $tamanho === 13) && str_starts_with($digitos, '55')) {
            return '+'.$digitos;
        }

        return $digitos;
    }

    public static function formatar(?string $e164): string
    {
        if ($e164 === null || $e164 === '') {
            return '';
        }

        if (! preg_match('/^\+55(\d{2})(\d{4,5})(\d{4})$/', $e164, $partes)) {
            return $e164;
        }

        return "({$partes[1]}) {$partes[2]}-{$partes[3]}";
    }
}
