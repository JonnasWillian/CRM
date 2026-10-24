<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Cancelar a renovação: a empresa usa até o fim do ciclo pago e não recebe
 * mais fatura. Preenchido = cancelamento agendado; ao vencer, a assinatura
 * passa a "cancelada" sem carência.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->timestamp('renovacao_cancelada_em')->nullable()->after('cancelada_em');
        });
    }

    public function down(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->dropColumn('renovacao_cancelada_em');
        });
    }
};
