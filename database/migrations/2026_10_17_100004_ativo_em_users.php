<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Membro desativado continua existindo (histórico, leads) mas não entra. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('ativo')->default(true)->after('tenant_id');
            $table->timestamp('desativado_em')->nullable()->after('ativo');
            $table->index(['tenant_id', 'ativo'], 'users_tenant_ativo_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_tenant_ativo_idx');
            $table->dropColumn(['ativo', 'desativado_em']);
        });
    }
};
