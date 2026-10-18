<?php

namespace App\Support\Csv;

/**
 * Proteção contra injeção de fórmula em CSV (OWASP "CSV Injection").
 *
 * Nome, descrição e afins vêm de usuários e de planilhas de terceiros. Uma
 * célula que começa com = + - @ (ou tab/CR) é lida como fórmula pelo Excel
 * e pelo LibreOffice ao abrir o arquivo; `=HYPERLINK(...)` ou `=cmd|...`
 * num nome de lead viraria um link ou um comando na máquina de quem
 * exportou. O apóstrofo na frente faz a planilha tratar a célula como texto.
 *
 * `desarmar` é o caminho de volta: a importação tira o apóstrofo que a
 * exportação pôs, para a ida e volta não acumular apóstrofos.
 */
final class CelulaSegura
{
    private const PERIGOSOS = '/^[=+\-@\t\r]/';

    public static function armar(mixed $valor): mixed
    {
        return is_string($valor) && preg_match(self::PERIGOSOS, $valor) ? "'".$valor : $valor;
    }

    /** @param  list<mixed>  $linha */
    public static function linha(array $linha): array
    {
        return array_map(self::armar(...), $linha);
    }

    public static function desarmar(string $valor): string
    {
        return str_starts_with($valor, "'") && preg_match(self::PERIGOSOS, substr($valor, 1)) ? substr($valor, 1) : $valor;
    }
}
