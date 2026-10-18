<?php

namespace App\Services\Importacao;

use App\Models\Importacao;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Lê o arquivo enviado: codificação (UTF-8 ou Windows-1252, o padrão do
 * Excel em português), separador (; , ou tab), cabeçalhos, 5 linhas de
 * amostra e o total. Grava uma cópia em UTF-8 sem BOM para o job.
 */
class PreviaDeImportacao
{
    public const AMOSTRA = 5;

    public function __invoke(UploadedFile $arquivo, User $autor): Importacao
    {
        $bruto = (string) file_get_contents($arquivo->getRealPath());
        $codificacao = mb_check_encoding($bruto, 'UTF-8') ? 'UTF-8' : 'Windows-1252';
        $texto = $codificacao === 'UTF-8' ? $bruto : mb_convert_encoding($bruto, 'UTF-8', 'Windows-1252');
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);

        $primeira = strtok($texto, "\n") ?: '';
        $delimitador = collect([';', ',', "\t"])->sortByDesc(fn ($d) => substr_count($primeira, $d))->first();

        $linhas = self::ler($texto, $delimitador);
        $cabecalhos = array_map(fn ($c) => trim((string) $c), array_shift($linhas) ?? []);

        if ($cabecalhos === [] || count(array_filter($cabecalhos)) === 0) {
            throw ValidationException::withMessages(['arquivo' => 'O arquivo não tem cabeçalho. A primeira linha deve ter os nomes das colunas.']);
        }
        if ($linhas === []) {
            throw ValidationException::withMessages(['arquivo' => 'O arquivo não tem linhas para importar.']);
        }
        $max = (int) config('leads.importacao.max_linhas', 20000);
        if (count($linhas) > $max) {
            throw ValidationException::withMessages(['arquivo' => 'O arquivo tem '.count($linhas)." linhas; o limite por importação é {$max}. Divida a planilha."]);
        }

        $caminho = 'importacoes/'.Str::uuid().'.csv';
        Storage::disk('local')->put($caminho, $texto);

        $importacao = new Importacao([
            'user_id' => $autor->id,
            'arquivo' => $caminho,
            'nome_original' => $arquivo->getClientOriginalName(),
            'delimitador' => $delimitador,
            'codificacao' => $codificacao,
            'cabecalhos' => $cabecalhos,
            'amostra' => array_slice($linhas, 0, self::AMOSTRA),
            'mapeamento' => CamposDeImportacao::sugerir($cabecalhos),
            'status' => Importacao::AGUARDANDO_MAPEAMENTO,
            'total_linhas' => count($linhas),
        ]);
        $importacao->tenant_id = $autor->tenant_id;
        $importacao->save();

        return $importacao;
    }

    /** @return list<list<string>> linhas não vazias, sem o fim de linha */
    public static function ler(string $texto, string $delimitador): array
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $texto);
        rewind($stream);
        $linhas = [];
        while (($linha = fgetcsv($stream, 0, $delimitador, '"', '')) !== false) {
            if ($linha === [null] || count(array_filter($linha, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }
            $linhas[] = array_map(fn ($v) => (string) $v, $linha);
        }
        fclose($stream);

        return $linhas;
    }
}
