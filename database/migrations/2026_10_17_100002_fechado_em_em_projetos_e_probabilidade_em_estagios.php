<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - `projetos.fechado_em`: instante em que o projeto entrou num status ganho
 *   (mantido por ProjetoObserver). Backfill por `updated_at` de quem já está
 *   ganho.
 * - `estagios.probabilidade_padrao`: sugestão que preenche o lead quando ele
 *   entra no estágio sem probabilidade informada.
 * - Cores dos estágios na paleta clara (resources/css/theme.css). Só o
 *   valor EXATO do default antigo é trocado: cor diferente foi escolhida
 *   pelo tenant e não se mexe.
 */
return new class extends Migration
{
    private const DE_PARA = [
        '#499fca' => '#2b6cb0', // categórica 1
        '#bd8939' => '#9c6612', // categórica 2
        '#9268f3' => '#6b46c1', // categórica 3
        '#e5618d' => '#b83280', // categórica 4
        '#30ae77' => '#1a7544', // status ganho
        '#f15873' => '#b42318', // status perdido
    ];

    public function up(): void
    {
        Schema::table('projetos', function (Blueprint $table) {
            $table->timestamp('fechado_em')->nullable()->after('status_id');
            $table->index(['tenant_id', 'fechado_em'], 'projetos_tenant_fechado_idx');
        });

        DB::statement(<<<'SQL'
            UPDATE projetos p
            JOIN status s ON s.id = p.status_id
            SET p.fechado_em = p.updated_at
            WHERE s.is_won = 1 AND p.fechado_em IS NULL
        SQL);

        Schema::table('estagios', function (Blueprint $table) {
            $table->unsignedTinyInteger('probabilidade_padrao')->nullable()->after('cor');
        });

        foreach (self::DE_PARA as $antiga => $nova) {
            DB::table('estagios')->where('cor', $antiga)->update(['cor' => $nova]);
        }
    }

    public function down(): void
    {
        foreach (self::DE_PARA as $antiga => $nova) {
            DB::table('estagios')->where('cor', $nova)->update(['cor' => $antiga]);
        }

        Schema::table('estagios', fn (Blueprint $table) => $table->dropColumn('probabilidade_padrao'));

        Schema::table('projetos', function (Blueprint $table) {
            $table->dropIndex('projetos_tenant_fechado_idx');
            $table->dropColumn('fechado_em');
        });
    }
};
