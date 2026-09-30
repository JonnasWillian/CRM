<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadados que o upload conhece e jogava fora. `Perfil.vue` já exibe
 * `arquivo.tamanho` — até aqui sempre vazio, porque a coluna não existia.
 * Nullable: as linhas antigas só ganham valor quando o comando
 * `arquivos:migrar-para-privado` passar por elas.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['arquivos', 'projetoAnexos'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->unsignedBigInteger('tamanho')->nullable()->after('local');
                $table->string('mime', 100)->nullable()->after('tamanho');
            });
        }
    }

    public function down(): void
    {
        foreach (['arquivos', 'projetoAnexos'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn(['tamanho', 'mime']);
            });
        }
    }
};
