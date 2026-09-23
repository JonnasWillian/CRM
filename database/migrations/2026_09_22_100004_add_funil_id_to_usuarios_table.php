<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O lead passa a saber em que funil está.
 *
 * Redundante com `estagios.funil_id` em regime normal, e de propósito: é o que
 * permite ao Kanban filtrar os leads de um funil sem join, e é o que sustenta a
 * invariante "um lead vive em um funil por vez" mesmo enquanto o estágio é nulo
 * — um lead recém-criado sem estágio ainda pertence a um funil.
 *
 * A consistência entre os dois (o estágio do lead tem de ser do funil do lead)
 * é validada na escrita, em UsuarioRequest e em MoverLeadDeFunil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->unsignedBigInteger('funil_id')->nullable()->after('estagio_id');
            $table->index('funil_id', 'usuarios_funil_id_index');
            $table->foreign('funil_id', 'usuarios_funil_id_foreign')
                ->references('id')->on('funis')->restrictOnDelete();

            $table->index(['tenant_id', 'funil_id'], 'usuarios_tenant_id_funil_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign('usuarios_funil_id_foreign');
            $table->dropIndex('usuarios_funil_id_index');
            $table->dropIndex('usuarios_tenant_id_funil_id_index');
            $table->dropColumn('funil_id');
        });
    }
};
