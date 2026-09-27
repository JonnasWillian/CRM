<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Userarios;
use App\Http\Controllers\ProjetoController;
use App\Http\Controllers\arquivo;
use App\Http\Controllers\TarefaController;
use App\Http\Controllers\TarefaPadraoController;
use App\Http\Controllers\LeadAtividadeController;
use App\Http\Controllers\FunilController;
use App\Http\Controllers\MotivoPerdaController;
use App\Http\Controllers\PerdaRelatorioController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::post('/estagios', [Userarios::class, 'estagios']);
    Route::post('/status', [ProjetoController::class, 'getStatus']);

    // Funis — leitura é de todo agente (Kanban, cadastro de lead e o diálogo
    // de mover de funil precisam saber quais existem); escrita exige
    // configuracoes.manage.
    Route::get('/funis', [FunilController::class, 'index']);

    // Motivos de perda — leitura aberta porque o modal que pede o motivo ao
    // arrastar um card precisa da lista, e quem arrasta não administra a
    // configuração. Escrita exige configuracoes.manage.
    Route::get('/motivos-perda', [MotivoPerdaController::class, 'index']);

    // Relatório "por que perdemos": visão agregada do tenant inteiro, que é o
    // que leads.view-all separa de quem só enxerga a própria carteira.
    Route::get('/relatorios/perdas', [PerdaRelatorioController::class, 'resumo'])
        ->middleware('can:leads.view-all');

    Route::middleware('can:configuracoes.manage')->group(function () {
        // As rotas de caminho fixo vêm antes das de parâmetro: `reordenar`
        // declarada depois de `{funil}` nunca seria alcançada.
        Route::patch('/funis/reordenar', [FunilController::class, 'reordenar']);

        Route::post('/funis', [FunilController::class, 'store']);
        Route::put('/funis/{funil}', [FunilController::class, 'update']);
        Route::delete('/funis/{funil}', [FunilController::class, 'destroy']);
        Route::patch('/funis/{funil}/padrao', [FunilController::class, 'definirPadrao']);

        Route::patch('/funis/{funil}/estagios/reordenar', [FunilController::class, 'reordenarEstagios']);
        Route::post('/funis/{funil}/estagios', [FunilController::class, 'storeEstagio']);
        Route::put('/estagios/{estagio}', [FunilController::class, 'updateEstagio']);
        Route::delete('/estagios/{estagio}', [FunilController::class, 'destroyEstagio']);
        // withTrashed no binding: o alvo de restaurar é, por definição, um
        // estágio soft-deleted, que o binding padrão resolveria como 404.
        Route::patch('/estagios/{estagio}/restaurar', [FunilController::class, 'restaurarEstagio'])->withTrashed();

        // Caminho fixo antes do de parâmetro, senão `reordenar` seria lido
        // como um {motivo}.
        Route::patch('/motivos-perda/reordenar', [MotivoPerdaController::class, 'reordenar']);
        Route::post('/motivos-perda', [MotivoPerdaController::class, 'store']);
        Route::put('/motivos-perda/{motivo}', [MotivoPerdaController::class, 'update']);
        Route::delete('/motivos-perda/{motivo}', [MotivoPerdaController::class, 'destroy']);
        Route::patch('/motivos-perda/{motivo}/restaurar', [MotivoPerdaController::class, 'restaurar'])->withTrashed();
    });

    // Leads (usuarios)
    Route::post('/pegarUsuarios', [Userarios::class, 'view']);
    Route::post('/usuarios', [Userarios::class, 'create']);
    Route::get('/usuarioPerfil/{usuario}', [Userarios::class, 'viewUsuario']);
    Route::put('/usuarios/{usuario}', [Userarios::class, 'update']);
    Route::delete('/usuarios/{usuario}', [Userarios::class, 'destroy']);
    // Timeline montada em runtime. Substituída pelo activity log abaixo;
    // mantida durante a transição, até o frontend trocar.
    Route::get('/timeline/{usuario}', [Userarios::class, 'timeline']);
    Route::get('/leads/{usuario}/atividades', [LeadAtividadeController::class, 'index']);
    Route::post('/metricas', [Userarios::class, 'metricas']);
    Route::post('/kanban', [Userarios::class, 'kanban']);
    Route::patch('/kanban/settings', [Userarios::class, 'kanbanSettings']);
    Route::patch('/usuarios/{usuario}/estagio', [Userarios::class, 'patchEstagio']);
    Route::patch('/usuarios/{usuario}/funil', [Userarios::class, 'moverFunil']);

    // Anotações de Lead
    Route::get('/anotacao/{usuario}', [Userarios::class, 'viewAnotacao']);
    Route::post('/anotacao', [Userarios::class, 'createAnotacao']);
    Route::put('/anotacao/{anotacao}', [Userarios::class, 'updateAnotacao']);
    Route::delete('/anotacao/{anotacao}', [Userarios::class, 'destroyAnotacao']);

    // Anexos de Lead
    // only(['index', 'store', 'destroy']): o controller só implementa esses
    // três — nem update() nem show() existem. A rota de show() existia e
    // apontava para um método inexistente: GET /api/arquivos/{arquivo} batia
    // em "Call to undefined method" e virava 500 a cada chamada.
    Route::apiResource('arquivos', arquivo::class)->only(['index', 'store', 'destroy']);
    Route::post('buscarArquivo', [arquivo::class, 'index']);

    // Projetos
    Route::post('/projetos', [ProjetoController::class, 'view']);
    Route::post('/projeto', [ProjetoController::class, 'create']);
    Route::get('/projeto/{projeto}', [ProjetoController::class, 'viewProjeto']);
    Route::put('/projeto/{projeto}', [ProjetoController::class, 'update']);

    // Anotações de Projeto
    // {id} por verbo: no GET o id é do PROJETO, no PUT/DELETE é da própria
    // anotação — mesma URL, o binding resolve pelo nome do parâmetro.
    Route::get('/projetoAnotacao/{projeto}', [ProjetoController::class, 'viewAnotacao']);
    Route::post('/projetoAnotacao', [ProjetoController::class, 'createAnotacao']);
    Route::put('/projetoAnotacao/{projetoAnotacao}', [ProjetoController::class, 'updateAnotacao']);
    Route::delete('/projetoAnotacao/{projetoAnotacao}', [ProjetoController::class, 'destroyAnotacao']);

    // Anexos de Projeto (mesma armadilha do {id} por verbo)
    Route::get('/projetoAnexo/{projeto}', [ProjetoController::class, 'viewAnexo']);
    Route::post('/projetoAnexo', [ProjetoController::class, 'createAnexo']);
    Route::delete('/projetoAnexo/{projetoAnexo}', [ProjetoController::class, 'destroyAnexo']);

    // Tarefas de Lead
    Route::get('/tarefas/{usuario}', [TarefaController::class, 'index']);
    Route::post('/tarefas', [TarefaController::class, 'store']);
    Route::put('/tarefas/{tarefa}', [TarefaController::class, 'update']);
    Route::delete('/tarefas/{tarefa}', [TarefaController::class, 'destroy']);
    Route::post('/tarefasPendentes', [TarefaController::class, 'pendentes']);

    // Modelos de Tarefa
    Route::get('/tarefa-padroes',          [TarefaPadraoController::class, 'index']);
    Route::post('/tarefa-padroes',         [TarefaPadraoController::class, 'store']);
    Route::post('/tarefa-padroes/aplicar', [TarefaPadraoController::class, 'aplicar']);
    Route::put('/tarefa-padroes/{tarefaPadrao}',     [TarefaPadraoController::class, 'update']);
    Route::delete('/tarefa-padroes/{tarefaPadrao}',  [TarefaPadraoController::class, 'destroy']);
});
