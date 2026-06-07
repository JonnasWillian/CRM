<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projetoAnexos', function (Blueprint $table) {
            $table->timestamp('deleted_at')->after('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projetoAnexos', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};
