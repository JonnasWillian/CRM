<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('kanban_default_tag_id')
                  ->nullable()
                  ->after('remember_token')
                  ->constrained('tags')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['kanban_default_tag_id']);
            $table->dropColumn('kanban_default_tag_id');
        });
    }
};
