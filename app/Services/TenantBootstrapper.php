<?php

namespace App\Services;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Statu;
use App\Models\Tenant;

/**
 * Semeia o funil padrão, seus estágios e o conjunto de status de projeto para
 * um tenant recém-criado.
 *
 * Sem isto, um tenant novo (fluxo de /register) nasce sem funil nenhum:
 * `/api/funis` e `/api/status` retornam `[]`, o Kanban renderiza zero colunas
 * e as métricas classificam todo lead como "sem estágio"/"sem status".
 *
 * O conteúdo semeado é ponto de partida, não regra: a tela de configuração
 * existe para o tenant renomear, reordenar, retipar, arquivar e criar outros
 * funis. Nada no código depois daqui depende destes nomes.
 *
 * Todos os campos fora de $fillable (`tenant_id`, `is_default`) são setados via
 * atribuição direta de propriedade em vez de mass assignment — a mesma técnica
 * que o resto do projeto usa para contornar o guard sem reabrir essa porta
 * (`tenant_id` nunca entra em `$fillable`, Global Constraint da task de
 * multi-tenancy).
 */
class TenantBootstrapper
{
    public static function bootstrap(Tenant $tenant): void
    {
        $funil = new Funil();
        $funil->nome = 'Vendas';
        $funil->descricao = 'Funil padrão, criado no cadastro da empresa.';
        $funil->ordem = 1;
        $funil->is_default = true;
        $funil->tenant_id = $tenant->id;
        $funil->save();

        foreach (static::defaultEstagios() as $attributes) {
            $estagio = new Estagio();
            $estagio->funil_id = $funil->id;
            $estagio->descricao = $attributes['descricao'];
            $estagio->ordem = $attributes['ordem'];
            $estagio->tipo = $attributes['tipo'];
            $estagio->cor = $attributes['cor'];
            $estagio->tenant_id = $tenant->id;
            $estagio->save();
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

    /**
     * "Pausado" é `aberto`, não fechado: um negócio parado não foi ganho nem
     * perdido. O conjunto antigo o marcava como arquivado via `is_active`, e a
     * migração de backfill corrige isso para os tenants que já existiam.
     */
    protected static function defaultEstagios(): array
    {
        return [
            ['descricao' => 'Em captação',        'ordem' => 1, 'tipo' => Estagio::TIPO_ABERTO,  'cor' => '#60a5fa'],
            ['descricao' => 'Em negociacao',      'ordem' => 2, 'tipo' => Estagio::TIPO_ABERTO,  'cor' => '#ec4899'],
            ['descricao' => 'Em desenvolvimento', 'ordem' => 3, 'tipo' => Estagio::TIPO_ABERTO,  'cor' => '#f59e0b'],
            ['descricao' => 'Concluído',          'ordem' => 4, 'tipo' => Estagio::TIPO_GANHO,   'cor' => '#34d399'],
            ['descricao' => 'Pausado',            'ordem' => 5, 'tipo' => Estagio::TIPO_ABERTO,  'cor' => '#8b5cf6'],
            ['descricao' => 'Cancelado',          'ordem' => 6, 'tipo' => Estagio::TIPO_PERDIDO, 'cor' => '#ef4444'],
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
