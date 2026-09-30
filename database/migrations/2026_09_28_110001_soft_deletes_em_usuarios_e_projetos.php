<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lixeira para lead e projeto. Eram os dois únicos agregados principais sem
 * SoftDeletes — justamente os que mais têm filhos.
 *
 * ATENÇÃO ao down(): derrubar `deleted_at` DEVOLVE À VIDA tudo o que estiver
 * na lixeira — leads e projetos excluídos voltam a aparecer nas listagens,
 * sem aviso. Antes de um rollback, decida o que fazer com essas linhas
 * (apagar de vez ou aceitar que reapareçam).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['usuarios', 'projetos'] as $tabela) {
            Schema::table($tabela, fn (Blueprint $table) => $table->softDeletes());
        }
    }

    public function down(): void
    {
        foreach (['usuarios', 'projetos'] as $tabela) {
            Schema::table($tabela, fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
