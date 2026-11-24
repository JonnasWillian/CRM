<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Quando o chamado foi entregue ao suporte (FormSubmit aceitou, ou o e-mail entrou na fila). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chamados_de_suporte', fn (Blueprint $t) => $t->timestamp('entregue_em')->nullable());
    }

    public function down(): void
    {
        Schema::table('chamados_de_suporte', fn (Blueprint $t) => $t->dropColumn('entregue_em'));
    }
};
