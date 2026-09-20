<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Activity do spatie/laravel-activitylog, escopado por tenant.
 *
 * Registrado em config/activitylog.php (chave `activity_model`), então tudo que
 * o pacote grava ou lê passa por aqui e herda o TenantScope.
 *
 * Atenção ao usar fora do ciclo HTTP: o TenantScope é fail-closed. Um comando
 * de console que percorra vários tenants precisa chamar CurrentTenant::set()
 * para cada um, ou a primeira query lança RuntimeException.
 */
class Activity extends SpatieActivity
{
    use BelongsToTenant;

    public function lead()
    {
        return $this->belongsTo(Usuario::class, 'lead_id');
    }
}
