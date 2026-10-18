<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/*
 * Nomes do domínio no banco: anotacaos → anotacoes, projetoAnotacaos →
 * projeto_anotacoes, projetoAnexos → projeto_anexos. No MySQL o RENAME
 * leva junto índices e chaves estrangeiras (que mantêm os nomes antigos,
 * o que não afeta nada). Reversível.
 */
return new class extends Migration
{
    private const TABELAS = [
        'anotacaos' => 'anotacoes',
        'projetoAnotacaos' => 'projeto_anotacoes',
        'projetoAnexos' => 'projeto_anexos',
    ];

    public function up(): void
    {
        foreach (self::TABELAS as $de => $para) {
            if (Schema::hasTable($de) && ! Schema::hasTable($para)) {
                Schema::rename($de, $para);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABELAS as $de => $para) {
            if (Schema::hasTable($para) && ! Schema::hasTable($de)) {
                Schema::rename($para, $de);
            }
        }
    }
};
