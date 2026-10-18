<?php

namespace App\Console\Commands;

use App\Models\Importacao;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** Apaga os arquivos de importações concluídas há mais de N dias (o registro e os contadores ficam). */
class LimparImportacoes extends Command
{
    protected $signature = 'importacoes:limpar';

    protected $description = 'Remove os arquivos CSV de importações antigas';

    public function handle(): int
    {
        $limite = now()->subDays((int) config('leads.importacao.reter_arquivo_dias', 7));
        $total = 0;

        Importacao::withoutGlobalScopes()->where('updated_at', '<', $limite)->where('arquivo', '!=', '')
            ->chunkById(200, function ($importacoes) use (&$total) {
                foreach ($importacoes as $imp) {
                    Storage::disk('local')->delete($imp->arquivo);
                    $imp->forceFill(['arquivo' => ''])->saveQuietly();
                    $total++;
                }
            });

        $this->info("{$total} arquivo(s) removido(s).");

        return self::SUCCESS;
    }
}
