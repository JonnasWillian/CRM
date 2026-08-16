<?php

namespace App\Services;

use App\Models\Statu;
use App\Models\Tags;
use App\Models\Tenant;

/**
 * Semeia o conjunto padrão de tags/status para um tenant recém-criado.
 *
 * Sem isto, um tenant novo (fluxo de /register) nasce sem nenhuma tag/status:
 * `/api/tags` e `/api/status` retornam `[]`, o Kanban renderiza zero colunas
 * e `Userarios::metricas()` classifica todo lead como "sem tag"/"sem status".
 *
 * O mapeamento descricao -> is_active/is_won/is_lost abaixo é o mesmo
 * mapeamento semântico real estabelecido pela migração de dados legados em
 * database/migrations/2026_08_10_090003_map_legacy_tag_and_status_ids_to_semantic_columns.php
 * — aqui apenas replicamos o mesmo conjunto para um tenant novo, sem tocar
 * naquela migração (que só reclassifica as linhas legadas do tenant 1).
 *
 * Todos os campos abaixo (`tenant_id`, `is_active`, `is_won`, `is_lost`) são
 * setados via atribuição direta de propriedade em vez de mass assignment,
 * porque nenhum deles está em `$fillable` de `Tags`/`Statu` — atribuição
 * direta contorna o guard sem reabrir essa porta (`tenant_id` nunca entra em
 * `$fillable`, Global Constraint da task de multi-tenancy).
 */
class TenantBootstrapper
{
    public static function bootstrap(Tenant $tenant): void
    {
        foreach (static::defaultTags() as $attributes) {
            $tag = new Tags();
            $tag->descricao = $attributes['descricao'];
            $tag->ordem = $attributes['ordem'];
            $tag->is_active = $attributes['is_active'];
            $tag->tenant_id = $tenant->id;
            $tag->save();
        }

        foreach (static::defaultStatus() as $attributes) {
            $status = new Statu();
            $status->descricao = $attributes['descricao'];
            $status->ordem = $attributes['ordem'];
            $status->is_won = $attributes['is_won'];
            $status->is_lost = $attributes['is_lost'];
            $status->tenant_id = $tenant->id;
            $status->save();
        }
    }

    protected static function defaultTags(): array
    {
        return [
            ['descricao' => 'Em captação', 'ordem' => 1, 'is_active' => true],
            ['descricao' => 'Em negociacao', 'ordem' => 2, 'is_active' => true],
            ['descricao' => 'Em desenvolvimento', 'ordem' => 3, 'is_active' => true],
            ['descricao' => 'Concluído', 'ordem' => 4, 'is_active' => false],
            ['descricao' => 'Pausado', 'ordem' => 5, 'is_active' => false],
            ['descricao' => 'Cancelado', 'ordem' => 6, 'is_active' => false],
        ];
    }

    protected static function defaultStatus(): array
    {
        return [
            ['descricao' => 'Em analise', 'ordem' => 1, 'is_won' => false, 'is_lost' => false],
            ['descricao' => 'PRD em andamento', 'ordem' => 2, 'is_won' => false, 'is_lost' => false],
            ['descricao' => 'Em desenvolvimento', 'ordem' => 3, 'is_won' => false, 'is_lost' => false],
            ['descricao' => 'Pausado', 'ordem' => 4, 'is_won' => false, 'is_lost' => false],
            ['descricao' => 'Concluido', 'ordem' => 5, 'is_won' => true, 'is_lost' => false],
            ['descricao' => 'Cancelado', 'ordem' => 6, 'is_won' => false, 'is_lost' => true],
            ['descricao' => 'Em disputa', 'ordem' => 7, 'is_won' => false, 'is_lost' => false],
        ];
    }
}
