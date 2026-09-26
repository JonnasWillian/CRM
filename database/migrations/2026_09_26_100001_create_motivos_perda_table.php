<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de motivos de perda, configurável por tenant.
 *
 * Existe para que o relatório "por que perdemos" seja agrupável. Texto livre
 * não soma: "preço alto", "Preço muito alto" e "achou caro" são a mesma coisa
 * para quem lê e três linhas diferentes para quem conta. A observação livre
 * continua existindo, mas em `perdas.observacao`, ao lado do motivo — não no
 * lugar dele.
 *
 * Arquivar é soft delete, como em funis e estágios: um motivo aposentado
 * continua resolvendo o nome das perdas antigas (`withTrashed`), e some do
 * seletor de quem registra uma perda nova.
 *
 * Sem coluna `ativo` além do soft delete: duas formas de dizer "não ofereça
 * mais este" é a mesma armadilha que `is_active` + `tipo` foi nos estágios.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('motivos_perda', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('descricao');
            $table->unsignedBigInteger('ordem')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id', 'motivos_perda_tenant_id_index');
            $table->foreign('tenant_id', 'motivos_perda_tenant_id_foreign')
                ->references('id')->on('tenants')->restrictOnDelete();

            $table->index(['tenant_id', 'ordem'], 'motivos_perda_tenant_id_ordem_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('motivos_perda');
    }
};
