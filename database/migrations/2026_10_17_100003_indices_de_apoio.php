<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices para as consultas que o plano torna frequentes: anotações por lead
 * em ordem de data, tarefas pendentes por vencimento, status por resultado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anotacaos', fn (Blueprint $t) => $t->index(['usuario_id', 'created_at'], 'anotacaos_usuario_criada_idx'));
        Schema::table('tarefas', fn (Blueprint $t) => $t->index(['tenant_id', 'concluido', 'data_limite'], 'tarefas_tenant_concluido_limite_idx'));
        Schema::table('status', fn (Blueprint $t) => $t->index(['tenant_id', 'is_won', 'is_lost'], 'status_tenant_resultado_idx'));
    }

    public function down(): void
    {
        Schema::table('anotacaos', fn (Blueprint $t) => $t->dropIndex('anotacaos_usuario_criada_idx'));
        Schema::table('tarefas', fn (Blueprint $t) => $t->dropIndex('tarefas_tenant_concluido_limite_idx'));
        Schema::table('status', fn (Blueprint $t) => $t->dropIndex('status_tenant_resultado_idx'));
    }
};
