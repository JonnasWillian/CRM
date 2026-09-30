<?php

namespace App\Console\Commands;

use App\Models\arquivo;
use App\Models\ProjetoAnexo;
use App\Services\Arquivos\ArquivoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Leva os anexos gravados até a correção do P1 (disco `public`, pasta plana
 * `arquivos/`) para o disco privado, na pasta do tenant e do dono.
 *
 * Idempotente: linha cujo `local` já começa com `tenants/` é pulada. Nada é
 * apagado sem confirmação de cópia (tamanhos iguais), e arquivo em disco sem
 * linha no banco só sai com --limpar-orfaos. Roda sem tenant ativo, por isso
 * lê sem global scopes (TenantScope é fail-closed) e inclui a lixeira.
 */
class MigrarArquivosParaPrivado extends Command
{
    protected $signature = 'arquivos:migrar-para-privado
        {--dry-run : Só relata o que faria}
        {--limpar-orfaos : Apaga do disco público os arquivos sem linha no banco}';

    protected $description = 'Move os anexos do disco público para o privado, particionados por tenant';

    public function handle(): int
    {
        $simular = (bool) $this->option('dry-run');
        $publico = Storage::disk('public');
        $privado = Storage::disk(ArquivoService::DISCO);

        $migrados = [];
        $semArquivo = [];
        $referenciados = [];

        $fontes = [
            [arquivo::class, fn ($l) => ArquivoService::pastaDoLead($l->tenant_id, $l->usuario_id)],
            [ProjetoAnexo::class, fn ($l) => ArquivoService::pastaDoProjeto($l->tenant_id, $l->projeto_id)],
        ];

        foreach ($fontes as [$classe, $pasta]) {
            $classe::withoutGlobalScopes()->chunkById(200, function ($linhas) use (
                $simular, $publico, $privado, $pasta, &$migrados, &$semArquivo, &$referenciados
            ) {
                foreach ($linhas as $linha) {
                    $origem = $linha->local;
                    $referenciados[$origem] = true;

                    if (str_starts_with((string) $origem, 'tenants/')) {
                        continue;
                    }

                    if (! $publico->exists($origem)) {
                        $semArquivo[] = [class_basename($linha), $linha->id, $origem];
                        continue;
                    }

                    $destino = $pasta($linha).'/'.basename($origem);
                    $migrados[] = [class_basename($linha), $linha->id, $origem, $destino];

                    if ($simular) {
                        continue;
                    }

                    $privado->writeStream($destino, $publico->readStream($origem));

                    if ($privado->size($destino) !== $publico->size($origem)) {
                        $privado->delete($destino);
                        $this->error("Cópia divergente, mantido no público: {$origem}");
                        continue;
                    }

                    $linha->forceFill([
                        'local' => $destino,
                        'tamanho' => $privado->size($destino),
                        'mime' => $privado->mimeType($destino) ?: null,
                    ])->saveQuietly();

                    $publico->delete($origem);
                }
            });
        }

        $orfaos = collect($publico->allFiles('arquivos'))
            ->reject(fn ($caminho) => isset($referenciados[$caminho]))
            ->values();

        $this->table(['Tipo', 'Id', 'De', 'Para'], $migrados);
        $this->line(($simular ? '[dry-run] ' : '').count($migrados).' arquivo(s) '.($simular ? 'seriam migrados' : 'migrados').'.');

        if ($semArquivo) {
            $this->warn('Linhas cujo arquivo não existe no disco público (não alteradas):');
            $this->table(['Tipo', 'Id', 'Local'], $semArquivo);
        }

        if ($orfaos->isNotEmpty()) {
            $this->warn('Arquivos no disco público sem linha no banco:');
            $orfaos->each(fn ($c) => $this->line("  {$c}"));

            if ($this->option('limpar-orfaos') && ! $simular) {
                $publico->delete($orfaos->all());
                $this->info($orfaos->count().' órfão(s) removido(s).');
            }
        }

        return self::SUCCESS;
    }
}
