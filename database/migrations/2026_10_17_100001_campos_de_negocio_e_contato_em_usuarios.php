<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O lead passa a carregar o negócio (valor estimado, valor fechado,
 * probabilidade, previsão) e duas colunas de evento mantidas por observer:
 *
 * - `ultimo_contato_em`: última anotação ou movimento de estágio. Antes era
 *   subconsulta correlacionada em `anotacaos` a cada card do Kanban; como
 *   coluna, o filtro "parado há 14 dias" e a ordenação da grade usam índice.
 * - `fechado_em`: instante em que o lead entrou em ganho/perdido. "Fechado no
 *   período" lia `updated_at`, que muda a cada edição.
 *
 * O backfill é a melhor aproximação disponível para o passado: última
 * anotação (ou a última edição) para o contato; `updated_at` para o
 * fechamento de quem já está em estágio fechado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->decimal('valor_estimado', 12, 2)->nullable()->after('descricao');
            $table->decimal('valor_fechado', 12, 2)->nullable()->after('valor_estimado');
            $table->unsignedTinyInteger('probabilidade')->nullable()->after('valor_fechado');
            $table->date('previsao_fechamento')->nullable()->after('probabilidade');
            $table->timestamp('ultimo_contato_em')->nullable()->after('estagio_id');
            $table->timestamp('fechado_em')->nullable()->after('ultimo_contato_em');

            $table->index(['tenant_id', 'previsao_fechamento'], 'usuarios_tenant_previsao_idx');
            $table->index(['tenant_id', 'funil_id', 'estagio_id', 'ultimo_contato_em'], 'usuarios_tenant_funil_estagio_contato_idx');
            $table->index(['tenant_id', 'fechado_em'], 'usuarios_tenant_fechado_idx');
        });

        DB::statement(<<<'SQL'
            UPDATE usuarios u
            LEFT JOIN (
                SELECT usuario_id, MAX(created_at) AS ultima
                FROM anotacaos
                WHERE deleted_at IS NULL
                GROUP BY usuario_id
            ) a ON a.usuario_id = u.id
            SET u.ultimo_contato_em = COALESCE(a.ultima, u.updated_at, u.created_at)
        SQL);

        DB::statement(<<<'SQL'
            UPDATE usuarios u
            JOIN estagios e ON e.id = u.estagio_id
            SET u.fechado_em = u.updated_at
            WHERE e.tipo IN ('ganho', 'perdido') AND u.fechado_em IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropIndex('usuarios_tenant_previsao_idx');
            $table->dropIndex('usuarios_tenant_funil_estagio_contato_idx');
            $table->dropIndex('usuarios_tenant_fechado_idx');
            $table->dropColumn(['valor_estimado', 'valor_fechado', 'probabilidade', 'previsao_fechamento', 'ultimo_contato_em', 'fechado_em']);
        });
    }
};
