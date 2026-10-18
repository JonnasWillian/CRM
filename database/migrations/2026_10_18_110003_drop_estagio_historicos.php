<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * estagio_historicos foi substituída pelo activity log (110001 copiou o que
 * faltava). IRREVERSÍVEL nos dados: o down() recria a tabela vazia, só para
 * o schema voltar. Rode depois de um backup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('estagio_historicos');
    }

    public function down(): void
    {
        if (Schema::hasTable('estagio_historicos')) {
            return;
        }

        Schema::create('estagio_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->unsignedBigInteger('estagio_anterior_id')->nullable();
            $table->unsignedBigInteger('funil_anterior_id')->nullable();
            $table->unsignedBigInteger('estagio_novo_id')->nullable();
            $table->unsignedBigInteger('funil_novo_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }
};
