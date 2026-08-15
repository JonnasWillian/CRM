<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tags', fn (Blueprint $table) => $table->softDeletes());
        Schema::table('status', fn (Blueprint $table) => $table->softDeletes());
    }

    public function down(): void
    {
        Schema::table('tags', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });

        Schema::table('status', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
