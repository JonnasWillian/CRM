<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign(['tag_id']);
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('restrict');
        });

        Schema::table('projetos', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->foreign('status_id')->references('id')->on('status')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropForeign(['tag_id']);
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
        });

        Schema::table('projetos', function (Blueprint $table) {
            $table->dropForeign(['status_id']);
            $table->foreign('status_id')->references('id')->on('status')->onDelete('cascade');
        });
    }
};
