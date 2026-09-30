<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CASCADE -> RESTRICT em toda FK que aponta para dado da empresa.
 *
 * Com lixeira (110001), a aplicação nunca precisa de exclusão definitiva
 * nessas tabelas. RESTRICT transforma uma exclusão definitiva acidental —
 * um forceDelete, um DELETE manual, um agente removido — em erro alto, em vez
 * de uma limpeza silenciosa de carteira, histórico e anexos.
 *
 * Travado por tests/Feature/DataIntegrity/SemCascataDestrutivaTest.php.
 */
return new class extends Migration
{
    /** [tabela, coluna, tabela referenciada] */
    private const CHAVES = [
        ['anotacaos', 'usuario_id', 'usuarios'],
        ['arquivos', 'usuario_id', 'usuarios'],
        ['projetos', 'usuario_id', 'usuarios'],
        ['tarefas', 'usuario_id', 'usuarios'],
        ['estagio_historicos', 'usuario_id', 'usuarios'],
        ['projetoAnexos', 'projeto_id', 'projetos'],
        ['projetoAnotacaos', 'projeto_id', 'projetos'],
        ['usuarios', 'user_id', 'users'],
    ];

    public function up(): void
    {
        $this->trocar(fn ($fk) => $fk->restrictOnDelete());
    }

    public function down(): void
    {
        $this->trocar(fn ($fk) => $fk->cascadeOnDelete());
    }

    private function trocar(callable $regra): void
    {
        foreach (self::CHAVES as [$tabela, $coluna, $referencia]) {
            Schema::table($tabela, function (Blueprint $table) use ($coluna, $referencia, $regra) {
                $table->dropForeign([$coluna]);
                $regra($table->foreign($coluna)->references('id')->on($referencia));
            });
        }
    }
};
