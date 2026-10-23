<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Assinaturas\Assinaturas;
use Illuminate\Console\Command;

/** Ativa ou renova a assinatura de uma empresa à mão (pagamento recebido fora do sistema). */
class AtivarAssinatura extends Command
{
    protected $signature = 'assinatura:ativar {tenant : id da empresa} {plano : inicial|profissional|empresa} {--ciclo=mensal : mensal|anual}';

    protected $description = 'Ativa ou renova o plano de uma empresa';

    public function handle(Assinaturas $assinaturas): int
    {
        $tenant = Tenant::find($this->argument('tenant'));
        if ($tenant === null) {
            $this->error('Empresa não encontrada.');

            return self::FAILURE;
        }

        $a = $assinaturas->ativar($tenant, $this->argument('plano'), $this->option('ciclo'));
        $this->info("{$tenant->nome}: plano {$a->plano} ({$a->ciclo}) vigente até {$a->vigente_ate->format('d/m/Y')}.");

        return self::SUCCESS;
    }
}
