<?php

namespace App\Console\Commands;

use App\Services\Pagamentos\Asaas\CobrancasDoAsaas;
use Illuminate\Console\Command;

/** Confirma faturas pagas no Asaas cujo aviso não chegou. Agendado de hora em hora. */
class AsaasConciliar extends Command
{
    protected $signature = 'asaas:conciliar';

    protected $description = 'Confere no Asaas as faturas pendentes e confirma as que foram pagas';

    public function handle(CobrancasDoAsaas $cobrancas): int
    {
        if (! $cobrancas->ativo()) {
            $this->line('Integração com o Asaas desligada (sem ASAAS_API_KEY). Nada a conferir.');

            return self::SUCCESS;
        }

        ['confirmadas' => $confirmadas, 'falhas' => $falhas] = $cobrancas->conciliar();
        $this->info($confirmadas === 1 ? '1 fatura confirmada.' : "{$confirmadas} faturas confirmadas.");
        if ($falhas > 0) {
            $this->warn(($falhas === 1 ? '1 não pôde ser conferida' : "{$falhas} não puderam ser conferidas").' agora (detalhes no log). A próxima rodada tenta de novo.');
        }

        return self::SUCCESS;
    }
}
