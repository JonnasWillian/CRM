<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuario_tag_historicos', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained('tenants')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('usuario_tag_historicos', fn (Blueprint $table) => $table->dropConstrainedForeignId('tenant_id'));
    }
};
