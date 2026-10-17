<?php

use App\Http\Controllers\arquivo;
use App\Http\Controllers\FunilController;
use App\Http\Controllers\LeadAtividadeController;
use App\Http\Controllers\MotivoPerdaController;
use App\Http\Controllers\PerdaRelatorioController;
use App\Http\Controllers\ProjetoController;
use App\Http\Controllers\TarefaController;
use App\Http\Controllers\TarefaPadraoController;
use App\Http\Controllers\Userarios;
use Illuminate\Support\Facades\Route;

/*
 * Toda rota daqui tem nome `api.*`: o front chama a API por `route()` (Ziggy),
 * nunca por URL cravada. RotasNomeadasTest garante que nenhuma entra sem nome.
 *
 * Ids são numéricos (Route::pattern em bootstrap/app.php), então um caminho
 * fixo como `funis/reordenar` nunca é lido como `{funil}` — a ordem de
 * declaração deixou de importar para isso.
 */
Route::name('api.')->middleware(['auth', 'tenant'])->group(function () {
    Route::get('/estagios', [Userarios::class, 'estagios'])->name('estagios.index');
    Route::get('/status', [ProjetoController::class, 'getStatus'])->name('status.index');

    // Funis — leitura é de todo agente (Kanban, cadastro de lead e o diálogo
    // de mover de funil precisam saber quais existem); escrita exige
    // configuracoes.manage.
    Route::get('/funis', [FunilController::class, 'index'])->name('funis.index');

    // Motivos de perda — leitura aberta porque o modal que pede o motivo ao
    // arrastar um card precisa da lista, e quem arrasta não administra a
    // configuração. Escrita exige configuracoes.manage.
    Route::get('/motivos-perda', [MotivoPerdaController::class, 'index'])->name('motivos-perda.index');

    // Relatório "por que perdemos": visão agregada do tenant inteiro, que é o
    // que leads.view-all separa de quem só enxerga a própria carteira.
    Route::get('/relatorios/perdas', [PerdaRelatorioController::class, 'resumo'])
        ->middleware('can:leads.view-all')
        ->name('relatorios.perdas');

    Route::middleware('can:configuracoes.manage')->group(function () {
        Route::patch('/funis/reordenar', [FunilController::class, 'reordenar'])->name('funis.reordenar');

        Route::post('/funis', [FunilController::class, 'store'])->name('funis.store');
        Route::put('/funis/{funil}', [FunilController::class, 'update'])->name('funis.update');
        Route::delete('/funis/{funil}', [FunilController::class, 'destroy'])->name('funis.destroy');
        Route::patch('/funis/{funil}/padrao', [FunilController::class, 'definirPadrao'])->name('funis.padrao');

        Route::patch('/funis/{funil}/estagios/reordenar', [FunilController::class, 'reordenarEstagios'])->name('funis.estagios.reordenar');
        Route::post('/funis/{funil}/estagios', [FunilController::class, 'storeEstagio'])->name('funis.estagios.store');
        Route::put('/estagios/{estagio}', [FunilController::class, 'updateEstagio'])->name('estagios.update');
        Route::delete('/estagios/{estagio}', [FunilController::class, 'destroyEstagio'])->name('estagios.destroy');
        // withTrashed no binding: o alvo de restaurar é, por definição, um
        // estágio soft-deleted, que o binding padrão resolveria como 404.
        Route::patch('/estagios/{estagio}/restaurar', [FunilController::class, 'restaurarEstagio'])->withTrashed()->name('estagios.restaurar');

        Route::patch('/motivos-perda/reordenar', [MotivoPerdaController::class, 'reordenar'])->name('motivos-perda.reordenar');
        Route::post('/motivos-perda', [MotivoPerdaController::class, 'store'])->name('motivos-perda.store');
        Route::put('/motivos-perda/{motivo}', [MotivoPerdaController::class, 'update'])->name('motivos-perda.update');
        Route::delete('/motivos-perda/{motivo}', [MotivoPerdaController::class, 'destroy'])->name('motivos-perda.destroy');
        Route::patch('/motivos-perda/{motivo}/restaurar', [MotivoPerdaController::class, 'restaurar'])->withTrashed()->name('motivos-perda.restaurar');
    });

    // Leads (usuarios). Os nomes já são os definitivos (api.leads.*); as URIs
    // legadas (/usuarios, /usuarioPerfil) mudam na normalização de rotas.
    Route::get('/leads', [Userarios::class, 'index'])->name('leads.index');
    Route::post('/usuarios', [Userarios::class, 'create'])->name('leads.store');
    Route::get('/usuarioPerfil/{usuario}', [Userarios::class, 'viewUsuario'])->name('leads.show');
    Route::put('/usuarios/{usuario}', [Userarios::class, 'update'])->name('leads.update');
    Route::delete('/usuarios/{usuario}', [Userarios::class, 'destroy'])->name('leads.destroy');
    // withTrashed: sem ele o binding esconde justamente o lead que se quer
    // restaurar. A policy (restore) é quem decide.
    Route::patch('/usuarios/{usuario}/restaurar', [Userarios::class, 'restaurar'])->withTrashed()->name('leads.restaurar');
    // Deprecado: sem consumidor no front desde a fase 4 do activity log. Só o TimelineParityTest usa.
    Route::get('/timeline/{usuario}', [Userarios::class, 'timeline'])->name('timeline.show');
    Route::get('/leads/{usuario}/atividades', [LeadAtividadeController::class, 'index'])->name('leads.atividades.index');
    Route::get('/metricas', [Userarios::class, 'metricas'])->name('metricas');
    Route::get('/kanban', [Userarios::class, 'kanban'])->name('kanban.index');
    Route::patch('/kanban/settings', [Userarios::class, 'kanbanSettings'])->name('kanban.settings');
    Route::patch('/usuarios/{usuario}/estagio', [Userarios::class, 'patchEstagio'])->name('leads.estagio');
    Route::patch('/usuarios/{usuario}/funil', [Userarios::class, 'moverFunil'])->name('leads.funil');

    // Anotações de Lead
    Route::get('/anotacao/{usuario}', [Userarios::class, 'viewAnotacao'])->name('leads.anotacoes.index');
    Route::post('/anotacao', [Userarios::class, 'createAnotacao'])->name('leads.anotacoes.store');
    Route::put('/anotacao/{anotacao}', [Userarios::class, 'updateAnotacao'])->name('anotacoes.update');
    Route::delete('/anotacao/{anotacao}', [Userarios::class, 'destroyAnotacao'])->name('anotacoes.destroy');

    // Anexos de Lead
    // only(['index', 'store', 'destroy']): o controller só implementa esses
    // três — nem update() nem show() existem. A rota de show() existia e
    // apontava para um método inexistente: GET /api/arquivos/{arquivo} batia
    // em "Call to undefined method" e virava 500 a cada chamada.
    Route::apiResource('arquivos', arquivo::class)->only(['index', 'store', 'destroy']);

    // Projetos
    Route::get('/projetos', [ProjetoController::class, 'view'])->name('leads.projetos.index');
    Route::post('/projeto', [ProjetoController::class, 'create'])->name('leads.projetos.store');
    Route::get('/projeto/{projeto}', [ProjetoController::class, 'viewProjeto'])->name('projetos.show');
    Route::put('/projeto/{projeto}', [ProjetoController::class, 'update'])->name('projetos.update');
    Route::delete('/projeto/{projeto}', [ProjetoController::class, 'destroy'])->name('projetos.destroy');
    Route::patch('/projeto/{projeto}/restaurar', [ProjetoController::class, 'restaurar'])->withTrashed()->name('projetos.restaurar');

    // Anotações de Projeto
    // {id} por verbo: no GET o id é do PROJETO, no PUT/DELETE é da própria
    // anotação — mesma URL, o binding resolve pelo nome do parâmetro.
    Route::get('/projetoAnotacao/{projeto}', [ProjetoController::class, 'viewAnotacao'])->name('projetos.anotacoes.index');
    Route::post('/projetoAnotacao', [ProjetoController::class, 'createAnotacao'])->name('projetos.anotacoes.store');
    Route::put('/projetoAnotacao/{projetoAnotacao}', [ProjetoController::class, 'updateAnotacao'])->name('projeto-anotacoes.update');
    Route::delete('/projetoAnotacao/{projetoAnotacao}', [ProjetoController::class, 'destroyAnotacao'])->name('projeto-anotacoes.destroy');

    // Anexos de Projeto (mesma armadilha do {id} por verbo)
    Route::get('/projetoAnexo/{projeto}', [ProjetoController::class, 'viewAnexo'])->name('projetos.anexos.index');
    Route::post('/projetoAnexo', [ProjetoController::class, 'createAnexo'])->name('projetos.anexos.store');
    Route::delete('/projetoAnexo/{projetoAnexo}', [ProjetoController::class, 'destroyAnexo'])->name('projeto-anexos.destroy');

    // Tarefas de Lead
    Route::get('/tarefas/{usuario}', [TarefaController::class, 'index'])->name('leads.tarefas.index');
    Route::post('/tarefas', [TarefaController::class, 'store'])->name('leads.tarefas.store');
    Route::put('/tarefas/{tarefa}', [TarefaController::class, 'update'])->name('tarefas.update');
    Route::delete('/tarefas/{tarefa}', [TarefaController::class, 'destroy'])->name('tarefas.destroy');
    Route::get('/tarefasPendentes', [TarefaController::class, 'pendentes'])->name('tarefas.pendentes');

    // Modelos de Tarefa
    Route::get('/tarefa-padroes', [TarefaPadraoController::class, 'index'])->name('tarefa-padroes.index');
    Route::post('/tarefa-padroes', [TarefaPadraoController::class, 'store'])->name('tarefa-padroes.store');
    Route::post('/tarefa-padroes/aplicar', [TarefaPadraoController::class, 'aplicar'])->name('tarefa-padroes.aplicar');
    Route::put('/tarefa-padroes/{tarefaPadrao}', [TarefaPadraoController::class, 'update'])->name('tarefa-padroes.update');
    Route::delete('/tarefa-padroes/{tarefaPadrao}', [TarefaPadraoController::class, 'destroy'])->name('tarefa-padroes.destroy');
});
