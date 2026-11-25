<?php

namespace App\Support;

/**
 * Texto escrito por cliente (nome da pessoa, nome da empresa) dentro de
 * e-mail. Os e-mails em Markdown escapam HTML, mas `[texto](link)` e
 * `![](imagem)` ainda virariam link e imagem: um nome de empresa não pode
 * transformar o convite do sistema em phishing.
 */
class TextoSeguro
{
    /** Para o corpo de e-mail em Markdown: os sinais do Markdown saem literais. */
    public static function semMarkdown(?string $texto): string
    {
        $limpo = trim(preg_replace('/[\p{C}]+/u', ' ', (string) $texto) ?? '');

        return preg_replace('/([\\\\`*_{}\[\]()#+\-!|<>~])/u', '\\\\$1', $limpo) ?? '';
    }

    /** Para o assunto: uma linha, sem endereço de site, sem sinais de link, até 80 caracteres. */
    public static function paraAssunto(?string $texto): string
    {
        $limpo = preg_replace('/[\p{C}]+/u', ' ', (string) $texto) ?? '';
        $limpo = preg_replace('~(https?://|www\.)\S+~iu', '', $limpo) ?? '';
        $limpo = preg_replace('~\b[\w-]+(\.[\w-]+)+(/\S*)?~u', '', $limpo) ?? ''; // dominio.com/caminho
        $limpo = preg_replace('/[\\\\\[\]()<>!`*_|]+/u', ' ', $limpo) ?? '';

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $limpo) ?? ''), 0, 80);
    }
}
