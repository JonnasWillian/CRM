<?php

namespace Database\Seeders;

use App\Support\Autorizacao\PapeisPadrao;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Garante os papéis e permissões de PapeisPadrao. Pode rodar quantas vezes
 * quiser, inclusive em produção: cria o que falta e devolve a cada papel
 * exatamente as permissões do contrato. Não mexe em quem tem qual papel.
 */
class PapeisEPermissoesSeeder extends Seeder
{
    public function run(PermissionRegistrar $registrar): void
    {
        $registrar->forgetCachedPermissions();

        foreach (PapeisPadrao::PERMISSOES as $nome) {
            Permission::findOrCreate($nome, 'web');
        }

        foreach (PapeisPadrao::PAPEIS as $papel => $permissoes) {
            // Global (tenant_id null): com teams ligado, Role::create usaria o time ativo.
            $role = Role::query()->firstOrCreate(['name' => $papel, 'guard_name' => 'web', 'tenant_id' => null]);
            $role->syncPermissions($permissoes);
        }

        $registrar->forgetCachedPermissions();
        $this->command?->info('Papéis e permissões conferidos: '.implode(', ', array_keys(PapeisPadrao::PAPEIS)).'.');
    }
}
