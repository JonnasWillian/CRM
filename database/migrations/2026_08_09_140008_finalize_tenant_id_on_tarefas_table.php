<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `tarefas` MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL");

        Schema::table('tarefas', function (Blueprint $table) {
            $table->index(['tenant_id', 'usuario_id', 'data_limite']);
        });
    }

    public function down(): void
    {
        Schema::table('tarefas', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'usuario_id', 'data_limite']);
        });

        DB::statement("ALTER TABLE `tarefas` MODIFY `tenant_id` BIGINT UNSIGNED NULL");
    }
};
