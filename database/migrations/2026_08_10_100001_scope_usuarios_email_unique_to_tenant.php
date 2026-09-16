<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A unicidade de email de lead passa a ser por tenant.
 *
 * O UNIQUE global vinha da migration original de `usuarios`, de antes da
 * multi-tenancy. Mantê-lo faria o cadastro de um lead por um tenant impedir
 * que qualquer outro tenant cadastrasse o mesmo contato — um mesmo email
 * pode legitimamente ser lead de duas empresas distintas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropUnique('usuarios_email_unique');
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'email']);
            $table->unique('email');
        });
    }
};
