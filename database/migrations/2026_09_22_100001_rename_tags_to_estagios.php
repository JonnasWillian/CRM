<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 de "funis múltiplos": rename puro, sem nenhuma lógica nova.
 *
 * "Tag" sempre significou "estágio do lead" neste sistema — nunca houve
 * marcação livre. O nome só se sustentava porque o conjunto era fixo; com
 * funis customizáveis por tenant ele passa a colidir de frente com o conceito
 * de "estágio de um funil".
 *
 * Renomear tabela e coluna não basta: MySQL mantém os nomes ORIGINAIS das
 * constraints e índices após RENAME TABLE / RENAME COLUMN. Sem o tratamento
 * abaixo o banco ficaria com `usuarios_tag_id_foreign` apontando para
 * `estagios.id`, e o próximo `dropForeign(['estagio_id'])` — que o Laravel
 * resolve pelo nome derivado de tabela+coluna — falharia por não achar a
 * constraint. É esse descompasso que torna rename pela metade caro depois.
 *
 * Nota sobre índices de FK no MySQL: dropForeign() remove a constraint mas
 * DEIXA o índice de suporte com o nome antigo. Se ele não for removido, o
 * foreign() seguinte reaproveita esse índice em vez de criar um novo, e o
 * nome velho sobrevive. Por isso cada dropForeign aqui vem com seu dropIndex.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- 1. Soltar tudo que referencia `tags` ---
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign('usuarios_tag_id_foreign');
            $table->dropIndex('usuarios_tag_id_foreign');
            $table->dropIndex('usuarios_tenant_id_tag_id_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_kanban_default_tag_id_foreign');
            $table->dropIndex('users_kanban_default_tag_id_foreign');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->dropForeign('tags_tenant_id_foreign');
            $table->dropIndex('tags_tenant_id_foreign');
        });

        Schema::table('usuario_tag_historicos', function (Blueprint $table) {
            $table->dropForeign('usuario_tag_historicos_usuario_id_foreign');
            $table->dropIndex('usuario_tag_historicos_usuario_id_foreign');
            $table->dropForeign('usuario_tag_historicos_tenant_id_foreign');
            $table->dropIndex('usuario_tag_historicos_tenant_id_usuario_id_index');
        });

        // --- 2. Tabelas ---
        Schema::rename('tags', 'estagios');
        Schema::rename('usuario_tag_historicos', 'estagio_historicos');

        // --- 3. Colunas ---
        Schema::table('usuarios', fn (Blueprint $t) => $t->renameColumn('tag_id', 'estagio_id'));
        Schema::table('users', fn (Blueprint $t) => $t->renameColumn('kanban_default_tag_id', 'kanban_default_estagio_id'));
        Schema::table('estagio_historicos', function (Blueprint $table) {
            $table->renameColumn('tag_id_anterior', 'estagio_anterior_id');
            $table->renameColumn('tag_id_novo', 'estagio_novo_id');
        });

        // --- 4. Recriar constraints e índices com os nomes novos ---
        // Cada índice de suporte é criado EXPLICITAMENTE, com nome próprio,
        // antes da FK que o consome. Deixar o MySQL criá-lo sozinho funciona no
        // up() mas torna o down() indefinido: o nome gerado não é contratual, e
        // uma migração posterior que adicione um índice composto começando pela
        // mesma coluna passa a ser aceita como suporte da FK — momento em que
        // remover esse composto vira erro 1553 ("needed in a foreign key
        // constraint") e o rollback quebra no meio.
        Schema::table('estagios', function (Blueprint $table) {
            $table->index('tenant_id', 'estagios_tenant_id_index');
            $table->foreign('tenant_id', 'estagios_tenant_id_foreign')
                ->references('id')->on('tenants')->restrictOnDelete();
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->index('estagio_id', 'usuarios_estagio_id_index');
            $table->foreign('estagio_id', 'usuarios_estagio_id_foreign')
                ->references('id')->on('estagios')->restrictOnDelete();
            $table->index(['tenant_id', 'estagio_id'], 'usuarios_tenant_id_estagio_id_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('kanban_default_estagio_id', 'users_kanban_default_estagio_id_index');
            $table->foreign('kanban_default_estagio_id', 'users_kanban_default_estagio_id_foreign')
                ->references('id')->on('estagios')->nullOnDelete();
        });

        Schema::table('estagio_historicos', function (Blueprint $table) {
            $table->index('usuario_id', 'estagio_historicos_usuario_id_index');
            $table->foreign('usuario_id', 'estagio_historicos_usuario_id_foreign')
                ->references('id')->on('usuarios')->cascadeOnDelete();

            $table->index('tenant_id', 'estagio_historicos_tenant_id_index');
            $table->foreign('tenant_id', 'estagio_historicos_tenant_id_foreign')
                ->references('id')->on('tenants')->restrictOnDelete();

            $table->index(['tenant_id', 'usuario_id'], 'estagio_historicos_tenant_id_usuario_id_index');
        });

        // --- 5. Subject do activity log ---
        // O pacote grava o FQCN do model em `subject_type`. O backfill usa a
        // tripla (subject_type, subject_id, event) como chave de idempotência,
        // então deixar as linhas antigas apontando para a classe renomeada não
        // é cosmético: numa próxima execução de `activities:backfill-leads` a
        // chave não casaria e TODO evento histórico de troca de estágio seria
        // inserido de novo, duplicado.
        DB::table('activity_log')
            ->where('subject_type', 'App\\Models\\UsuarioTagHistorico')
            ->update(['subject_type' => 'App\\Models\\EstagioHistorico']);
    }

    public function down(): void
    {
        // Em cada tabela: primeiro as FKs, só depois os índices que as sustentam.
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign('usuarios_estagio_id_foreign');
            $table->dropIndex('usuarios_estagio_id_index');
            $table->dropIndex('usuarios_tenant_id_estagio_id_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_kanban_default_estagio_id_foreign');
            $table->dropIndex('users_kanban_default_estagio_id_index');
        });

        Schema::table('estagios', function (Blueprint $table) {
            $table->dropForeign('estagios_tenant_id_foreign');
            $table->dropIndex('estagios_tenant_id_index');
        });

        Schema::table('estagio_historicos', function (Blueprint $table) {
            $table->dropForeign('estagio_historicos_usuario_id_foreign');
            $table->dropForeign('estagio_historicos_tenant_id_foreign');
            $table->dropIndex('estagio_historicos_usuario_id_index');
            $table->dropIndex('estagio_historicos_tenant_id_index');
            $table->dropIndex('estagio_historicos_tenant_id_usuario_id_index');
        });

        Schema::rename('estagios', 'tags');
        Schema::rename('estagio_historicos', 'usuario_tag_historicos');

        Schema::table('usuarios', fn (Blueprint $t) => $t->renameColumn('estagio_id', 'tag_id'));
        Schema::table('users', fn (Blueprint $t) => $t->renameColumn('kanban_default_estagio_id', 'kanban_default_tag_id'));
        Schema::table('usuario_tag_historicos', function (Blueprint $table) {
            $table->renameColumn('estagio_anterior_id', 'tag_id_anterior');
            $table->renameColumn('estagio_novo_id', 'tag_id_novo');
        });

        Schema::table('tags', function (Blueprint $table) {
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->foreign('tag_id')->references('id')->on('tags')->restrictOnDelete();
            $table->index(['tenant_id', 'tag_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('kanban_default_tag_id')->references('id')->on('tags')->nullOnDelete();
        });

        Schema::table('usuario_tag_historicos', function (Blueprint $table) {
            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->index(['tenant_id', 'usuario_id']);
        });

        DB::table('activity_log')
            ->where('subject_type', 'App\\Models\\EstagioHistorico')
            ->update(['subject_type' => 'App\\Models\\UsuarioTagHistorico']);
    }
};
