<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `usuarios` MODIFY `tenant_id` BIGINT UNSIGNED NOT NULL");

        Schema::table('usuarios', function (Blueprint $table) {
            $table->index(['tenant_id', 'user_id']);
            $table->index(['tenant_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'user_id']);
            $table->dropIndex(['tenant_id', 'tag_id']);
        });

        DB::statement("ALTER TABLE `usuarios` MODIFY `tenant_id` BIGINT UNSIGNED NULL");
    }
};
