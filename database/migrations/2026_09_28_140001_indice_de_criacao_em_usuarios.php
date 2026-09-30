<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A listagem ordena e filtra por data de criação dentro do tenant. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', fn (Blueprint $t) => $t->index(['tenant_id', 'created_at']));
    }

    public function down(): void
    {
        Schema::table('usuarios', fn (Blueprint $t) => $t->dropIndex(['tenant_id', 'created_at']));
    }
};
