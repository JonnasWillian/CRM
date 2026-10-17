<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Uma importação de leads por CSV: arquivo, mapeamento, progresso e resultado. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('arquivo');
            $table->string('nome_original');
            $table->char('delimitador', 1)->default(';');
            $table->string('codificacao', 20)->default('UTF-8');
            $table->json('cabecalhos')->nullable();
            $table->json('amostra')->nullable();
            $table->json('mapeamento')->nullable();
            $table->json('opcoes')->nullable();
            $table->string('status', 30)->default('aguardando_mapeamento');
            $table->unsignedInteger('total_linhas')->default(0);
            $table->unsignedInteger('processadas')->default(0);
            $table->unsignedInteger('criadas')->default(0);
            $table->unsignedInteger('atualizadas')->default(0);
            $table->unsignedInteger('ignoradas')->default(0);
            $table->unsignedInteger('com_erro')->default(0);
            $table->json('erros')->nullable();
            $table->text('mensagem_falha')->nullable();
            $table->timestamp('iniciada_em')->nullable();
            $table->timestamp('concluida_em')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'created_at'], 'importacoes_tenant_criada_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacoes');
    }
};
