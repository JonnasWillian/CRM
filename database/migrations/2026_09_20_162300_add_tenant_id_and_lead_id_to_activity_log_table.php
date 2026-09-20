<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acrescenta à tabela do spatie/laravel-activitylog as duas colunas que este
 * projeto precisa. Roda antes de qualquer escrita no log, então a tabela nunca
 * existe sem elas.
 *
 * `lead_id` existe porque os eventos vêm de sete tabelas de origem diferentes;
 * sem uma coluna própria, "atividades do lead X" viraria uma união de sete
 * consultas. O subject continua sendo a linha de origem — o lead_id é só o
 * agrupamento.
 *
 * `lead_id` não recebe foreign key de propósito: nenhuma das tabelas filhas de
 * `usuarios` (anotacaos, tarefas, arquivos, projetos) tem FK para ela neste
 * schema, e criar uma só aqui, com restrict, impediria excluir um lead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->foreignId('tenant_id')->after('id')->constrained('tenants')->restrictOnDelete();
            $table->unsignedBigInteger('lead_id')->nullable()->after('tenant_id');

            // Exatamente o acesso do endpoint paginado: filtra tenant + lead,
            // ordena por data.
            $table->index(['tenant_id', 'lead_id', 'created_at'], 'activity_log_tenant_lead_created_index');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex('activity_log_tenant_lead_created_index');
            $table->dropColumn('lead_id');
            $table->dropConstrainedForeignId('tenant_id');
        });
    }
};
