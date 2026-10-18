<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Colunas polimórficas guardam aliases ('lead', 'projeto'…) em vez do nome da
 * classe. Com Relation::enforceMorphMap (AppServiceProvider), renomear
 * `arquivo` para `Arquivo` ou `StatusProjeto` para `StatusProjeto` deixa de exigir
 * migração de dados de log.
 *
 * Precisa rodar no MESMO deploy em que o morph map entra: sem o map, 'lead'
 * não resolve; sem a migration, 'App\Models\Usuario' não resolve.
 */
return new class extends Migration
{
    private const DE_PARA = [
        'App\\Models\\Usuario' => 'lead',
        'App\\Models\\Projeto' => 'projeto',
        'App\\Models\\Anotacao' => 'anotacao',
        'App\\Models\\arquivo' => 'arquivo',
        'App\\Models\\ProjetoAnotacao' => 'projeto_anotacao',
        'App\\Models\\ProjetoAnexo' => 'projeto_anexo',
        'App\\Models\\Tarefa' => 'tarefa',
        'App\\Models\\TarefaPadrao' => 'tarefa_padrao',
        'App\\Models\\EstagioHistorico' => 'estagio_historico',
        'App\\Models\\User' => 'user',
    ];

    public function up(): void
    {
        foreach (self::DE_PARA as $classe => $alias) {
            DB::table('activity_log')->where('subject_type', $classe)->update(['subject_type' => $alias]);
            DB::table('activity_log')->where('causer_type', $classe)->update(['causer_type' => $alias]);
            DB::table('perdas')->where('perdivel_type', $classe)->update(['perdivel_type' => $alias]);
            // spatie/permission também é polimórfico: sem isto, toda atribuição
            // de papel existente sumiria no deploy do morph map.
            DB::table('model_has_roles')->where('model_type', $classe)->update(['model_type' => $alias]);
            DB::table('model_has_permissions')->where('model_type', $classe)->update(['model_type' => $alias]);
        }
    }

    public function down(): void
    {
        foreach (self::DE_PARA as $classe => $alias) {
            DB::table('activity_log')->where('subject_type', $alias)->update(['subject_type' => $classe]);
            DB::table('activity_log')->where('causer_type', $alias)->update(['causer_type' => $classe]);
            DB::table('perdas')->where('perdivel_type', $alias)->update(['perdivel_type' => $classe]);
            DB::table('model_has_roles')->where('model_type', $alias)->update(['model_type' => $classe]);
            DB::table('model_has_permissions')->where('model_type', $alias)->update(['model_type' => $classe]);
        }
    }
};
