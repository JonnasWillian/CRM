<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O histórico passa a registrar também a troca de funil.
 *
 * Sem estas colunas, "saiu de Fechado (Vendas) e entrou em Onboarding
 * (Pós-venda)" só seria reconstruível fazendo join com `estagios` — que é
 * mutável: o estágio pode ser renomeado, arquivado ou movido depois, e o
 * histórico passaria a contar outra história. Guardar o funil na própria linha
 * mantém o registro verdadeiro no tempo em que foi escrito.
 *
 * Sem FK, pelo mesmo motivo das colunas de estágio já existentes: o histórico
 * precisa sobreviver à remoção física do funil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estagio_historicos', function (Blueprint $table) {
            $table->unsignedBigInteger('funil_anterior_id')->nullable()->after('estagio_anterior_id');
            $table->unsignedBigInteger('funil_novo_id')->nullable()->after('estagio_novo_id');
        });
    }

    public function down(): void
    {
        Schema::table('estagio_historicos', function (Blueprint $table) {
            $table->dropColumn(['funil_anterior_id', 'funil_novo_id']);
        });
    }
};
