<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Primeiro acesso: o perfil da empresa (respostas do questionário), o estado
 * do passo a passo de cada usuário, a visão preferida do Dashboard e a origem
 * do lead. Ver docs/superpowers/specs/2026-11-01-onboarding-personalizado-design.md.
 *
 * Empresas que já existem não ganham linha em perfis_de_empresa: só o
 * cadastro novo cria a linha pendente que leva ao questionário. Usuários que
 * já existem ficam com o passo a passo concluído.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfis_de_empresa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->json('respostas')->nullable();
            $table->timestamp('respondido_em')->nullable();
            $table->timestamp('pulado_em')->nullable();
            $table->foreignId('respondido_por')->nullable()->constrained('users')->nullOnDelete();
            $table->string('telefone', 20)->nullable();
            $table->boolean('aceita_dicas_whatsapp')->default(false);
            $table->timestamp('aceite_dicas_em')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('tour_estado', 12)->default('pendente');
            $table->string('tour_versao', 8)->default('completa');
            $table->string('tour_passo', 40)->nullable();
            $table->string('dashboard_visao', 8)->nullable();
        });
        DB::table('users')->update(['tour_estado' => 'concluido']);

        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('origem', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('origem');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tour_estado', 'tour_versao', 'tour_passo', 'dashboard_visao']);
        });
        Schema::dropIfExists('perfis_de_empresa');
    }
};
