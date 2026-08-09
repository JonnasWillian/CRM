<?php

namespace App\Models\Scopes;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restringe toda query do model ao tenant ativo (CurrentTenant).
 *
 * A coluna tenant_id é qualificada pelo nome da tabela propositalmente:
 * alguns controllers (ex.: Userarios::view()/kanban()) usam whereColumn()
 * e addSelect() com subqueries correlacionadas. Sem a qualificação, um
 * `where('tenant_id', ...)` não qualificado poderia ficar ambíguo entre
 * a query principal e a subquery, ou se referir à tabela errada.
 *
 * Se não houver tenant ativo, CurrentTenant::get() lança RuntimeException
 * (fail-closed): nenhuma query passa sem tenant definido.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        app(CurrentTenant::class)->get();

        $builder->where($model->getTable() . '.tenant_id', app(CurrentTenant::class)->id());
    }
}
