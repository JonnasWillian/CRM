<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('status', function (Blueprint $table) {
            $table->boolean('is_won')->default(false)->after('ordem');
            $table->boolean('is_lost')->default(false)->after('is_won');
        });
    }

    public function down(): void
    {
        Schema::table('status', fn (Blueprint $table) => $table->dropColumn(['is_won', 'is_lost']));
    }
};
