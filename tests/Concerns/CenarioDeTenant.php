<?php

namespace Tests\Concerns;

use App\Models\Funil;
use App\Models\Projeto;
use App\Models\Statu;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Spatie\Permission\PermissionRegistrar;

/**
 * O mesmo cenário que cada teste de autorização monta à mão: tenant ativo,
 * team do spatie apontado para ele, agentes com papel, lead no funil padrão.
 *
 * `ativar()` existe à parte porque testes com dois tenants precisam trocar o
 * tenant corrente entre um fixture e outro — o TenantScope é fail-closed e o
 * BelongsToTenant preenche tenant_id a partir dele.
 */
trait CenarioDeTenant
{
    protected function novoTenant(): Tenant
    {
        $tenant = Tenant::factory()->create();
        $this->ativar($tenant);

        return $tenant;
    }

    protected function ativar(Tenant $tenant): void
    {
        app(CurrentTenant::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
    }

    protected function agente(Tenant $tenant, string $papel = 'vendedor'): User
    {
        $this->ativar($tenant);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole($papel);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    protected function lead(Tenant $tenant, User $dono, array $attrs = []): Usuario
    {
        $this->ativar($tenant);
        $funil = Funil::where('is_default', true)->first()
            ?? Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);

        return Usuario::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'user_id' => $dono->id,
            'funil_id' => $funil->id,
        ], $attrs));
    }

    protected function projeto(Usuario $lead, array $attrs = []): Projeto
    {
        $status = Statu::factory()->create(['tenant_id' => $lead->tenant_id]);

        $projeto = new Projeto();
        $projeto->fill(array_merge([
            'nome' => 'Proposta inicial',
            'usuario_id' => $lead->id,
            'status_id' => $status->id,
        ], $attrs));
        $projeto->tenant_id = $lead->tenant_id;
        $projeto->save();

        return $projeto;
    }
}
