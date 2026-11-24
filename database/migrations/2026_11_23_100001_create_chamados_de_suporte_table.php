<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Respostas do assistente de ajuda e feedback; `acionou_suporte` diz se virou e-mail. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chamados_de_suporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 20);
            $table->string('area', 20);
            $table->string('impacto', 20)->nullable();
            $table->text('mensagem');
            $table->unsignedTinyInteger('nota')->nullable();
            $table->string('email_contato');
            $table->boolean('acionou_suporte')->default(false);
            $table->string('pagina')->nullable();
            $table->string('navegador')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chamados_de_suporte');
    }
};
