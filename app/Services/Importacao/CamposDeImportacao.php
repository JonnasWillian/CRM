<?php

namespace App\Services\Importacao;

use Illuminate\Support\Str;

/**
 * Os campos que a importação entende (os mesmos cabeçalhos da exportação,
 * para ida e volta) e os apelidos que a prévia reconhece em planilhas reais.
 */
final class CamposDeImportacao
{
    public const CAMPOS = [
        'nome' => ['nome', 'name', 'cliente', 'lead', 'razaosocial', 'contato', 'empresa'],
        'email' => ['email', 'mail', 'emaildocontato'],
        'telefone' => ['telefone', 'fone', 'celular', 'whatsapp', 'phone', 'tel'],
        'descricao' => ['descricao', 'observacao', 'observacoes', 'obs', 'notas', 'anotacao'],
        'funil' => ['funil', 'pipeline'],
        'estagio' => ['estagio', 'etapa', 'fase', 'status'],
        'valor_estimado' => ['valorestimado', 'valor', 'valordonegocio'],
        'probabilidade' => ['probabilidade', 'chance'],
        'previsao_fechamento' => ['previsaofechamento', 'previsao', 'previsaodefechamento'],
        'responsavel' => ['responsavel', 'vendedor', 'dono', 'owner'],
    ];

    public static function normalizar(string $cabecalho): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($cabecalho)));
    }

    /** @param list<string> $cabecalhos  @return array<string,int> campo => índice da coluna */
    public static function sugerir(array $cabecalhos): array
    {
        $mapa = [];
        foreach ($cabecalhos as $i => $cabecalho) {
            $chave = self::normalizar($cabecalho);
            foreach (self::CAMPOS as $campo => $apelidos) {
                if (! isset($mapa[$campo]) && in_array($chave, $apelidos, true)) {
                    $mapa[$campo] = $i;
                    break;
                }
            }
        }

        return $mapa;
    }
}
