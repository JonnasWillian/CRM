<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `projetoAnotacaos` MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL");

        Schema::table('projetoAnotacaos', function (Blueprint $table) {
            $table->index(['tenant_id', 'projeto_id']);
        });
    }

    public function down(): void
    {
        Schema::table('projetoAnotacaos', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'projeto_id']);
        });

        DB::statement("ALTER TABLE `projetoAnotacaos` MODIFY `tenant_id` BIGINT UNSIGNED NULL");
    }
};
