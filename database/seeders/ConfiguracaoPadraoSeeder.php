<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Assinaturas\Assinaturas;
use App\Services\TenantBootstrapper;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Seeder;

/**
 * Completa a configuração padrão de todas as empresas: funil "Vendas" com os
 * estágios, motivos de perda, status de projeto, assinatura em teste e ao
 * menos um admin ativo. Só cria o que falta; o que a empresa já configurou
 * fica como está. Seguro para produção e para rodar de novo.
 */
class ConfiguracaoPadraoSeeder extends Seeder
{
    public function run(CurrentTenant $atual, Assinaturas $assinaturas): void
    {
        $this->call(PapeisEPermissoesSeeder::class);

        $time = getPermissionsTeamId();
        $completadas = 0;
        $total = 0;

        try {
            foreach (Tenant::query()->orderBy('id')->cursor() as $tenant) {
                $total++;
                $atual->set($tenant);
                setPermissionsTeamId($tenant->id);

                $criado = TenantBootstrapper::completar($tenant);
                $criado['assinatura'] = $assinaturas->da($tenant)->wasRecentlyCreated;
                $criado['admin'] = $this->garantirAdmin($tenant);

                if (in_array(true, $criado, true)) {
                    $completadas++;
                    $this->command?->line("  {$tenant->nome}: ".implode(', ', array_keys(array_filter($criado))));
                }
            }
        } finally {
            $atual->clear();
            setPermissionsTeamId($time);
        }

        $this->command?->info("Configuração padrão conferida em {$total} empresa(s); {$completadas} completada(s).");
    }

    /** A empresa nunca fica sem admin ativo: o usuário ativo mais antigo assume. */
    private function garantirAdmin(Tenant $tenant): bool
    {
        if (User::role('admin')->where('tenant_id', $tenant->id)->where('ativo', true)->exists()) {
            return false;
        }

        $maisAntigo = User::where('tenant_id', $tenant->id)->where('ativo', true)->orderBy('id')->first();
        $maisAntigo?->assignRole('admin');

        return $maisAntigo !== null;
    }
}
