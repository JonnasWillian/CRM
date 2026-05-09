<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projetos', function (Blueprint $table) {
            $table->decimal('preco', 10, 2)->after('descricao')->nullable();
            $table->timestamp('data_inicial')->after('descricao')->nullable();
            $table->timestamp('data_final')->after('descricao')->nullable();
            $table->boolean('parcelas')->after('descricao')->nullable()->default(false);
            $table->bigInteger('qtd_parcelas')->after('descricao')->nullable()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('tag_id');
        });
    }
};
