<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convite para entrar na equipe. O token em claro só existe no e-mail; o
 * banco guarda o hash (sha256). Aceito, cancelado ou expirado deixam de ser
 * pendentes mas ficam como registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('convites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('email');
            $table->string('papel', 20);
            $table->char('token_hash', 64)->unique();
            $table->foreignId('convidado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expira_em');
            $table->timestamp('aceito_em')->nullable();
            $table->timestamp('cancelado_em')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'email'], 'convites_tenant_email_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('convites');
    }
};
