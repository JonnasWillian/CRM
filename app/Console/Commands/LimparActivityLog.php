<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

/**
 * Apaga do activity log o que passou da retenção, tenant a tenant.
 *
 * O `activitylog:clean` do pacote não serve aqui: `Activity` herda o
 * TenantScope fail-closed, e o comando do pacote consulta sem tenant ativo —
 * lança RuntimeException na primeira query. Este percorre os tenants
 * (inclusive os arquivados, que também acumulam log) ativando cada um.
 */
class LimparActivityLog extends Command
{
    protected $signature = 'activity:limpar {--dias= : Retenção em dias (padrão: activitylog.delete_records_older_than_days)}';

    protected $description = 'Apaga registros do activity log mais antigos que a retenção configurada, em todos os tenants';

    public function handle(CurrentTenant $tenantAtual): int
    {
        $dias = (int) ($this->option('dias') ?: config('activitylog.delete_records_older_than_days'));

        if ($dias <= 0) {
            $this->error('Retenção inválida: informe --dias maior que zero ou configure activitylog.delete_records_older_than_days.');

            return self::FAILURE;
        }

        $limite = now()->subDays($dias);
        $total = 0;

        foreach (Tenant::withTrashed()->cursor() as $tenant) {
            $tenantAtual->set($tenant);
            $total += Activity::where('created_at', '<', $limite)->delete();
        }

        $tenantAtual->clear();

        $this->info("{$total} registro(s) com mais de {$dias} dias apagado(s).");

        return self::SUCCESS;
    }
}
