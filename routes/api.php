<?php

use App\Http\Controllers\Api\AnotacaoController;
use App\Http\Controllers\Api\ArquivoController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LeadAtividadeController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\ProjetoAnexoController;
use App\Http\Controllers\Api\ProjetoAnotacaoController;
use App\Http\Controllers\Api\ProjetoController;
use App\Http\Controllers\Api\StatusProjetoController;
use App\Http\Controllers\Api\TarefaController;
use App\Http\Controllers\Api\TarefaPadraoController;
use App\Http\Controllers\FunilController;
use App\Http\Controllers\MotivoPerdaController;
use App\Http\Controllers\PerdaRelatorioController;
use App\Http\Controllers\Userarios;
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
    Route::get('/estagios', [Userarios::class, 'estagios'])->name('estagios.index');
    Route::get('/status', [StatusProjetoController::class, 'index'])->name('status.index');
    Route::get('/funis', [FunilController::class, 'index'])->name('funis.index');
    Route::get('/motivos-perda', [MotivoPerdaController::class, 'index'])->name('motivos-perda.index');

    // ── Relatórios ──
    Route::get('/relatorios/perdas', [PerdaRelatorioController::class, 'resumo'])
        ->middleware('can:leads.view-all')
        ->name('relatorios.perdas');
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
    Route::get('/leads/{usuario}', [LeadController::class, 'show'])->name('leads.show');
    Route::put('/leads/{usuario}', [LeadController::class, 'update'])->name('leads.update');
    Route::delete('/leads/{usuario}', [LeadController::class, 'destroy'])->name('leads.destroy');
    // withTrashed: sem ele o binding esconde justamente o lead que se quer
    // restaurar. A policy (restore) é quem decide.
    Route::patch('/leads/{usuario}/restaurar', [LeadController::class, 'restaurar'])->withTrashed()->name('leads.restaurar');
    Route::patch('/leads/{usuario}/estagio', [LeadController::class, 'estagio'])->name('leads.estagio');
    Route::patch('/leads/{usuario}/funil', [LeadController::class, 'funil'])->name('leads.funil');
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
    // Legado do Dashboard atual (sai na Fase 3b junto com a tela).
    Route::get('/tarefasPendentes', [TarefaController::class, 'pendentes'])->name('tarefas.pendentes');
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

    // ── Kanban (sai para Api\KanbanController na Fase 3c) ──
    Route::get('/kanban', [Userarios::class, 'kanban'])->name('kanban.index');
    Route::patch('/kanban/settings', [Userarios::class, 'kanbanSettings'])->name('kanban.settings');

    // Deprecado: sem consumidor no front. Só o TimelineParityTest usa (sai na Fase 5).
    Route::get('/timeline/{usuario}', [Userarios::class, 'timeline'])->name('timeline.show');
});
