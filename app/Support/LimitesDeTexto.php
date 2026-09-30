<?php

namespace App\Support;

/**
 * Tamanho máximo de cada texto livre, num lugar só: a validação usa estes
 * números, as colunas cabem neles (TEXT = 65.535 bytes; 10.000 caracteres em
 * UTF-8 de até 4 bytes cabem com folga até 16k — ver migration 130001), e o
 * front recebe os mesmos valores por prop compartilhada.
 */
final class LimitesDeTexto
{
    public const NOME = 255;
    public const EMAIL = 255;
    public const DESCRICAO = 5000;
    public const ANOTACAO = 10000;

    public static function paraOFront(): array
    {
        return [
            'nome' => self::NOME,
            'email' => self::EMAIL,
            'descricao' => self::DESCRICAO,
            'anotacao' => self::ANOTACAO,
        ];
    }
}
