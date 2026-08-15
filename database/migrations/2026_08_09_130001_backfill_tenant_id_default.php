<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tabelas = ['users', 'usuarios', 'tags', 'status', 'projetos', 'anotacaos', 'arquivos', 'tarefas', 'tarefa_padroes', 'projetoAnotacaos', 'projetoAnexos', 'usuario_tag_historicos'];
        foreach ($tabelas as $tabela) {
            DB::table($tabela)->whereNull('tenant_id')->update(['tenant_id' => 1]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
