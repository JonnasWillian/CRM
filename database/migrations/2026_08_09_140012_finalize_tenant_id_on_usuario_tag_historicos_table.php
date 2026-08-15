<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `usuario_tag_historicos` MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL");

        Schema::table('usuario_tag_historicos', function (Blueprint $table) {
            $table->index(['tenant_id', 'usuario_id']);
        });
    }

    public function down(): void
    {
        Schema::table('usuario_tag_historicos', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'usuario_id']);
        });

        DB::statement("ALTER TABLE `usuario_tag_historicos` MODIFY `tenant_id` BIGINT UNSIGNED NULL");
    }
};
