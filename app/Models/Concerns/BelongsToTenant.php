<?php

namespace App\Models\Concerns;

use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aplica o multi-tenancy a um model: registra o TenantScope global e
 * preenche tenant_id automaticamente ao criar, a partir do tenant ativo.
 *
 * O uso de empty() ao invés de sobrescrever incondicionalmente permite
 * setar tenant_id explicitamente antes de create() quando necessário
 * (ex.: seeders, jobs que operam fora do tenant "current").
 */
trait BelongsToTenant
{
    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = app(CurrentTenant::class)->id();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
