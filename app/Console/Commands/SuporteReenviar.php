<?php

namespace App\Console\Commands;

use App\Models\ChamadoDeSuporte;
use App\Services\Suporte\EntregaDeChamado;
use Illuminate\Console\Command;

/** Tenta de novo os chamados que pediram suporte e não foram entregues. */
class SuporteReenviar extends Command
{
    protected $signature = 'suporte:reenviar {id? : Só este chamado, mesmo já entregue}';

    protected $description = 'Reenvia ao suporte os chamados que não foram entregues';

    public function handle(EntregaDeChamado $entrega): int
    {
        $q = ChamadoDeSuporte::withoutGlobalScopes()->where('acionou_suporte', true);
        $q = $this->argument('id') ? $q->whereKey($this->argument('id')) : $q->whereNull('entregue_em');

        foreach ($q->orderBy('id')->get() as $chamado) {
            $ok = $entrega->entregar($chamado);
            $this->line("Chamado #{$chamado->id}: ".($ok ? 'entregue.' : 'falhou (detalhes no log).'));
        }

        return self::SUCCESS;
    }
}
