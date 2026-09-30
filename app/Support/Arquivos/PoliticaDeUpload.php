<?php

namespace App\Support\Arquivos;

/**
 * O que o CRM aceita como anexo, num lugar só.
 *
 * Lista de PERMITIDOS: um tipo perigoso novo não passa só porque ninguém
 * lembrou de proibi-lo. Fora da lista, de propósito:
 *   - html/htm/svg: servidos do mesmo domínio viram XSS armazenado;
 *   - executáveis e scripts;
 *   - compactados: escondem qualquer um dos anteriores.
 *
 * `mimes` do Laravel compara a extensão ADIVINHADA PELO CONTEÚDO
 * (UploadedFile::guessExtension), não a do nome — um .exe renomeado para
 * .pdf é recusado.
 */
final class PoliticaDeUpload
{
    public const MAX_KB = 10240;

    public const EXTENSOES = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods',
        'txt', 'csv',
    ];

    public static function regras(): array
    {
        return ['required', 'file', 'mimes:'.implode(',', self::EXTENSOES), 'max:'.self::MAX_KB];
    }

    public static function mensagens(string $campo = 'arquivo'): array
    {
        return [
            "{$campo}.required" => 'Selecione um arquivo.',
            "{$campo}.file" => 'Envie um arquivo válido.',
            // Disparada quando o PHP descarta o upload (upload_max_filesize /
            // post_max_size) antes de a validação ver o conteúdo.
            "{$campo}.uploaded" => 'O arquivo passa do limite de upload do servidor. Envie um arquivo menor.',
            "{$campo}.mimes" => 'Tipo de arquivo não permitido. Envie PDF, imagem, documento, planilha, apresentação, TXT ou CSV.',
            "{$campo}.max" => 'O arquivo pode ter no máximo '.intdiv(self::MAX_KB, 1024).' MB.',
        ];
    }
}
