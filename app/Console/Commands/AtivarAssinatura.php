<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Assinaturas\Faturamento;
use Illuminate\Console\Command;

/**
 * Ativa ou renova à mão, sem fatura prévia (acordo comercial, cortesia).
 * Registra uma fatura paga para o histórico não ter buracos. Pagamento de
 * fatura já gerada: use fatura:pagar.
 */
class AtivarAssinatura extends Command
{
    protected $signature = 'assinatura:ativar {tenant : id da empresa} {plano : inicial|profissional|empresa} {--ciclo=mensal : mensal|anual}';

    protected $description = 'Ativa ou renova o plano de uma empresa';

    public function handle(Faturamento $faturamento): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if ($tenant === null) {
            $this->error('Empresa não encontrada.');

            return self::FAILURE;
        }

        $fatura = $faturamento->gerar($tenant, $this->argument('plano'), $this->option('ciclo'), 'manual', \Illuminate\Support\Carbon::today());
        $faturamento->pagar($fatura, 'assinatura:ativar');
        $a = \App\Models\Assinatura::where('tenant_id', $tenant->id)->first();
        $this->info("{$tenant->nome}: plano {$a->plano} ({$a->ciclo}) vigente até {$a->vigente_ate->format('d/m/Y')}.");

        return self::SUCCESS;
    }
}
