<?php

namespace App\Console\Commands;

use App\Services\Leads\NormalizacaoDeTelefones;
use Illuminate\Console\Command;

class NormalizarTelefones extends Command
{
    protected $signature = 'telefones:normalizar {--dry-run : Só relata o que faria}';

    protected $description = 'Converte os telefones dos leads para E.164 e lista os que não são reconhecidos';

    public function handle(NormalizacaoDeTelefones $normalizacao): int
    {
        $simular = (bool) $this->option('dry-run');
        $resultado = $normalizacao->executar($simular);

        $this->line(($simular ? '[dry-run] ' : '').$resultado['alterados'].' telefone(s) '.($simular ? 'seriam alterados' : 'alterado(s)').'.');

        if ($resultado['invalidos']) {
            $this->warn('Não reconhecidos (mantidos como estão; corrigir pela tela do lead):');
            $this->table(['Lead', 'Telefone'], $resultado['invalidos']);
        }

        return self::SUCCESS;
    }
}
