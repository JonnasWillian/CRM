<?php

use App\Http\Controllers\Api\AnotacaoController;
use App\Http\Controllers\Api\ArquivoController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EquipeController;
use App\Http\Controllers\Api\EstagioController;
use App\Http\Controllers\Api\FunilController;
use App\Http\Controllers\Api\ImportacaoController;
use App\Http\Controllers\Api\KanbanController;
use App\Http\Controllers\Api\LeadAtividadeController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LixeiraController;
use App\Http\Controllers\Api\MotivoPerdaController;
use App\Http\Controllers\Api\PerdaRelatorioController;
use App\Http\Controllers\Api\ProjetoAnexoController;
use App\Http\Controllers\Api\ProjetoAnotacaoController;
use App\Http\Controllers\Api\ProjetoController;
use App\Http\Controllers\Api\StatusProjetoController;
use App\Http\Controllers\Api\TarefaController;
use App\Http\Controllers\Api\TarefaPadraoController;
use Illuminate\Support\Facades\Route;

/*
 * Toda rota daqui tem nome `api.*`: o front chama a API por `route()` (Ziggy),
 * nunca por URL cravada. RotasNomeadasTest garante que nenhuma entra sem nome.
 *
 * Forma: recursos no plural, o PAI na URL de index/store
 * (`/leads/{usuario}/anotacoes`) e rota rasa para update/destroy
 * (`/anotacoes/{anotacao}`). Nada de id de dono no corpo. Ids são numéricos
 * (Route::pattern em bootstrap/app.php), então `tarefas/resumo` nunca é lido
 * como `{usuario}`.
 */
Route::name('api.')->middleware(['auth', 'tenant'])->group(function () {
    // ── Catálogos ──
    Route::get('/estagios', [EstagioController::class, 'index'])->name('estagios.index');
    Route::get('/status', [StatusProjetoController::class, 'index'])->name('status.index');
    Route::get('/funis', [FunilController::class, 'index'])->name('funis.index');
    Route::get('/motivos-perda', [MotivoPerdaController::class, 'index'])->name('motivos-perda.index');

    // ── Relatórios ──
    Route::middleware('can:leads.view-all')->group(function () {
        Route::get('/relatorios/perdas', [PerdaRelatorioController::class, 'resumo'])->name('relatorios.perdas');
        Route::get('/relatorios/perdas/detalhe', [PerdaRelatorioController::class, 'detalhe'])->name('relatorios.perdas.detalhe');
        Route::get('/relatorios/visao-geral', [\App\Http\Controllers\Api\RelatorioController::class, 'visaoGeral'])->name('relatorios.visao-geral');
    });
    Route::get('/metricas', [DashboardController::class, 'metricas'])->name('metricas');

    // ── Configuração (escrita exige configuracoes.manage) ──
    Route::middleware('can:configuracoes.manage')->group(function () {
        Route::patch('/funis/reordenar', [FunilController::class, 'reordenar'])->name('funis.reordenar');
        Route::post('/funis', [FunilController::class, 'store'])->name('funis.store');
        Route::put('/funis/{funil}', [FunilController::class, 'update'])->name('funis.update');
        Route::delete('/funis/{funil}', [FunilController::class, 'destroy'])->name('funis.destroy');
        Route::patch('/funis/{funil}/padrao', [FunilController::class, 'definirPadrao'])->name('funis.padrao');
        Route::patch('/funis/{funil}/restaurar', [FunilController::class, 'restaurar'])->withTrashed()->name('funis.restaurar');

        Route::patch('/funis/{funil}/estagios/reordenar', [FunilController::class, 'reordenarEstagios'])->name('funis.estagios.reordenar');
        Route::post('/funis/{funil}/estagios', [FunilController::class, 'storeEstagio'])->name('funis.estagios.store');
        Route::put('/estagios/{estagio}', [FunilController::class, 'updateEstagio'])->name('estagios.update');
        Route::delete('/estagios/{estagio}', [FunilController::class, 'destroyEstagio'])->name('estagios.destroy');
        Route::patch('/estagios/{estagio}/restaurar', [FunilController::class, 'restaurarEstagio'])->withTrashed()->name('estagios.restaurar');

        Route::patch('/motivos-perda/reordenar', [MotivoPerdaController::class, 'reordenar'])->name('motivos-perda.reordenar');
        Route::post('/motivos-perda', [MotivoPerdaController::class, 'store'])->name('motivos-perda.store');
        Route::put('/motivos-perda/{motivo}', [MotivoPerdaController::class, 'update'])->name('motivos-perda.update');
        Route::delete('/motivos-perda/{motivo}', [MotivoPerdaController::class, 'destroy'])->name('motivos-perda.destroy');
        Route::patch('/motivos-perda/{motivo}/restaurar', [MotivoPerdaController::class, 'restaurar'])->withTrashed()->name('motivos-perda.restaurar');
    });

    // ── Leads ──
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');
    Route::post('/leads', [LeadController::class, 'store'])->name('leads.store');
    Route::get('/leads/exportar', [LeadController::class, 'exportar'])->name('leads.exportar');
    // Ações em massa: ids[] (até 200) ou todos=true + filtros da grade.
    Route::post('/leads/mover-estagio', [LeadController::class, 'moverEstagio'])->name('leads.mover-estagio');
    Route::post('/leads/lixeira', [LeadController::class, 'lixeira'])->name('leads.lixeira');
    Route::post('/leads/transferir', [LeadController::class, 'transferir'])->name('leads.transferir');
    Route::get('/leads/{usuario}', [LeadController::class, 'show'])->name('leads.show');
    Route::put('/leads/{usuario}', [LeadController::class, 'update'])->name('leads.update');
    Route::delete('/leads/{usuario}', [LeadController::class, 'destroy'])->name('leads.destroy');
    // withTrashed: sem ele o binding esconde justamente o lead que se quer
    // restaurar. A policy (restore) é quem decide.
    Route::patch('/leads/{usuario}/restaurar', [LeadController::class, 'restaurar'])->withTrashed()->name('leads.restaurar');
    Route::patch('/leads/{usuario}/estagio', [LeadController::class, 'estagio'])->name('leads.estagio');
    Route::patch('/leads/{usuario}/funil', [LeadController::class, 'funil'])->name('leads.funil');
    Route::patch('/leads/{usuario}/responsavel', [LeadController::class, 'responsavel'])->name('leads.responsavel');
    Route::get('/leads/{usuario}/atividades', [LeadAtividadeController::class, 'index'])->name('leads.atividades.index');

    // Filhos do lead: o pai na URL, rota rasa para editar/apagar.
    Route::get('/leads/{usuario}/anotacoes', [AnotacaoController::class, 'index'])->name('leads.anotacoes.index');
    Route::post('/leads/{usuario}/anotacoes', [AnotacaoController::class, 'store'])->name('leads.anotacoes.store');
    Route::put('/anotacoes/{anotacao}', [AnotacaoController::class, 'update'])->name('anotacoes.update');
    Route::delete('/anotacoes/{anotacao}', [AnotacaoController::class, 'destroy'])->name('anotacoes.destroy');

    Route::get('/leads/{usuario}/arquivos', [ArquivoController::class, 'index'])->name('leads.arquivos.index');
    Route::post('/leads/{usuario}/arquivos', [ArquivoController::class, 'store'])->name('leads.arquivos.store');
    Route::delete('/arquivos/{arquivo}', [ArquivoController::class, 'destroy'])->name('arquivos.destroy');

    Route::get('/leads/{usuario}/projetos', [ProjetoController::class, 'index'])->name('leads.projetos.index');
    Route::post('/leads/{usuario}/projetos', [ProjetoController::class, 'store'])->name('leads.projetos.store');
    Route::get('/projetos/{projeto}', [ProjetoController::class, 'show'])->name('projetos.show');
    Route::put('/projetos/{projeto}', [ProjetoController::class, 'update'])->name('projetos.update');
    Route::delete('/projetos/{projeto}', [ProjetoController::class, 'destroy'])->name('projetos.destroy');
    Route::patch('/projetos/{projeto}/restaurar', [ProjetoController::class, 'restaurar'])->withTrashed()->name('projetos.restaurar');

    Route::get('/projetos/{projeto}/anotacoes', [ProjetoAnotacaoController::class, 'index'])->name('projetos.anotacoes.index');
    Route::post('/projetos/{projeto}/anotacoes', [ProjetoAnotacaoController::class, 'store'])->name('projetos.anotacoes.store');
    Route::put('/projeto-anotacoes/{projetoAnotacao}', [ProjetoAnotacaoController::class, 'update'])->name('projeto-anotacoes.update');
    Route::delete('/projeto-anotacoes/{projetoAnotacao}', [ProjetoAnotacaoController::class, 'destroy'])->name('projeto-anotacoes.destroy');

    Route::get('/projetos/{projeto}/anexos', [ProjetoAnexoController::class, 'index'])->name('projetos.anexos.index');
    Route::post('/projetos/{projeto}/anexos', [ProjetoAnexoController::class, 'store'])->name('projetos.anexos.store');
    Route::delete('/projeto-anexos/{projetoAnexo}', [ProjetoAnexoController::class, 'destroy'])->name('projeto-anexos.destroy');

    // ── Tarefas: globais, resumo e por lead ──
    Route::get('/tarefas', [TarefaController::class, 'listar'])->name('tarefas.index');
    Route::get('/tarefas/resumo', [TarefaController::class, 'resumo'])->name('tarefas.resumo');
    Route::post('/tarefas/concluir', [TarefaController::class, 'concluir'])->name('tarefas.concluir');
    Route::post('/tarefas/excluir', [TarefaController::class, 'excluir'])->name('tarefas.excluir');
    Route::get('/leads/{usuario}/tarefas', [TarefaController::class, 'porLead'])->name('leads.tarefas.index');
    Route::post('/leads/{usuario}/tarefas', [TarefaController::class, 'store'])->name('leads.tarefas.store');
    Route::post('/leads/{usuario}/tarefas/aplicar-modelos', [TarefaPadraoController::class, 'aplicar'])->name('leads.tarefas.aplicar-modelos');
    Route::put('/tarefas/{tarefa}', [TarefaController::class, 'update'])->name('tarefas.update');
    Route::delete('/tarefas/{tarefa}', [TarefaController::class, 'destroy'])->name('tarefas.destroy');

    // ── Modelos de tarefa (do próprio agente) ──
    Route::get('/tarefa-padroes', [TarefaPadraoController::class, 'index'])->name('tarefa-padroes.index');
    Route::post('/tarefa-padroes', [TarefaPadraoController::class, 'store'])->name('tarefa-padroes.store');
    Route::put('/tarefa-padroes/{tarefaPadrao}', [TarefaPadraoController::class, 'update'])->name('tarefa-padroes.update');
    Route::delete('/tarefa-padroes/{tarefaPadrao}', [TarefaPadraoController::class, 'destroy'])->name('tarefa-padroes.destroy');

    // ── Importação de leads (quem vê a empresa inteira) ──
    Route::middleware('can:leads.view-all')->group(function () {
        Route::get('/importacoes', [ImportacaoController::class, 'index'])->name('importacoes.index');
        Route::post('/importacoes', [ImportacaoController::class, 'store'])->name('importacoes.store');
        Route::get('/importacoes/{importacao}', [ImportacaoController::class, 'show'])->name('importacoes.show');
        Route::post('/importacoes/{importacao}/iniciar', [ImportacaoController::class, 'iniciar'])->name('importacoes.iniciar');
        Route::get('/importacoes/{importacao}/erros', [ImportacaoController::class, 'erros'])->name('importacoes.erros');
    });

    // ── Lixeira (o que o agente enxerga) ──
    Route::get('/lixeira', [LixeiraController::class, 'index'])->name('lixeira.index');
    Route::post('/lixeira/restaurar', [LixeiraController::class, 'restaurar'])->name('lixeira.restaurar');

    // ── Equipe ──
    Route::get('/equipe/opcoes', [EquipeController::class, 'opcoes'])->middleware('can:leads.view-all')->name('equipe.opcoes');
    Route::middleware('can:agentes.manage')->group(function () {
        Route::get('/equipe', [EquipeController::class, 'index'])->name('equipe.index');
        Route::post('/equipe/convites', [EquipeController::class, 'convidar'])->name('equipe.convites.store');
        Route::post('/equipe/convites/{convite}/reenviar', [EquipeController::class, 'reenviar'])->name('equipe.convites.reenviar');
        Route::delete('/equipe/convites/{convite}', [EquipeController::class, 'cancelar'])->name('equipe.convites.destroy');
        Route::patch('/equipe/{agente}/papel', [EquipeController::class, 'papel'])->name('equipe.papel');
        Route::patch('/equipe/{agente}/desativar', [EquipeController::class, 'desativar'])->name('equipe.desativar');
        Route::patch('/equipe/{agente}/reativar', [EquipeController::class, 'reativar'])->name('equipe.reativar');
    });

    // ── Kanban: quadro agrupado, página de uma coluna e coluna padrão ──
    Route::get('/kanban', [KanbanController::class, 'index'])->name('kanban.index');
    // withTrashed: uma coluna arquivada ainda em uso continua no quadro e pode "carregar mais".
    Route::get('/kanban/colunas/{estagio}', [KanbanController::class, 'coluna'])->withTrashed()->name('kanban.coluna');
    Route::patch('/kanban/settings', [KanbanController::class, 'settings'])->name('kanban.settings');
});
