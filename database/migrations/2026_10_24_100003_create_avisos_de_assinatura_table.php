<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * O que a rotina de assinaturas já fez por empresa: cada aviso enviado e
 * cada fatura de renovação gerada, com a referência do prazo (data ou id da
 * fatura). O índice único é a garantia de "uma vez só", mesmo com a rotina
 * rodando duas vezes ou em paralelo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('avisos_de_assinatura', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('tipo', 40);
            $table->string('referencia', 40);
            $table->timestamp('enviado_em');

            $table->unique(['tenant_id', 'tipo', 'referencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos_de_assinatura');
    }
};
