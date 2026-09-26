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
    Route::get('/usuarioPerfil/{id}', [Userarios::class, 'viewUsuario']);
    Route::put('/usuarios/{id}', [Userarios::class, 'update']);
    Route::delete('/usuarios/{usuario}', [Userarios::class, 'destroy']);
    // Timeline montada em runtime. Substituída pelo activity log abaixo;
    // mantida durante a transição, até o frontend trocar.
    Route::get('/timeline/{id}', [Userarios::class, 'timeline']);
    Route::get('/leads/{usuario}/atividades', [LeadAtividadeController::class, 'index']);
    Route::post('/metricas', [Userarios::class, 'metricas']);
    Route::post('/kanban', [Userarios::class, 'kanban']);
    Route::patch('/kanban/settings', [Userarios::class, 'kanbanSettings']);
    Route::patch('/usuarios/{id}/estagio', [Userarios::class, 'patchEstagio']);
    Route::patch('/usuarios/{id}/funil', [Userarios::class, 'moverFunil']);

    // Anotações de Lead
    Route::get('/anotacao/{id}', [Userarios::class, 'viewAnotacao']);
    Route::post('/anotacao', [Userarios::class, 'createAnotacao']);
    Route::put('/anotacao/{id}', [Userarios::class, 'updateAnotacao']);
    Route::delete('/anotacao/{id}', [Userarios::class, 'destroyAnotacao']);

    // Anexos de Lead
    Route::apiResource('arquivos', arquivo::class);
    Route::post('buscarArquivo', [arquivo::class, 'index']);

    // Projetos
    Route::post('/projetos', [ProjetoController::class, 'view']);
    Route::post('/projeto', [ProjetoController::class, 'create']);
    Route::get('/projeto/{id}', [ProjetoController::class, 'viewProjeto']);
    Route::put('/projeto/{id}', [ProjetoController::class, 'update']);

    // Anotações de Projeto
    Route::get('/projetoAnotacao/{id}', [ProjetoController::class, 'viewAnotacao']);
    Route::post('/projetoAnotacao', [ProjetoController::class, 'createAnotacao']);
    Route::put('/projetoAnotacao/{id}', [ProjetoController::class, 'updateAnotacao']);
    Route::delete('/projetoAnotacao/{id}', [ProjetoController::class, 'destroyAnotacao']);

    // Anexos de Projeto
    Route::get('/projetoAnexo/{id}', [ProjetoController::class, 'viewAnexo']);
    Route::post('/projetoAnexo', [ProjetoController::class, 'createAnexo']);
    Route::delete('/projetoAnexo/{id}', [ProjetoController::class, 'destroyAnexo']);

    // Tarefas de Lead
    Route::get('/tarefas/{usuarioId}', [TarefaController::class, 'index']);
    Route::post('/tarefas', [TarefaController::class, 'store']);
    Route::put('/tarefas/{id}', [TarefaController::class, 'update']);
    Route::delete('/tarefas/{id}', [TarefaController::class, 'destroy']);
    Route::post('/tarefasPendentes', [TarefaController::class, 'pendentes']);

    // Modelos de Tarefa
    Route::get('/tarefa-padroes',          [TarefaPadraoController::class, 'index']);
    Route::post('/tarefa-padroes',         [TarefaPadraoController::class, 'store']);
    Route::post('/tarefa-padroes/aplicar', [TarefaPadraoController::class, 'aplicar']);
    Route::put('/tarefa-padroes/{id}',     [TarefaPadraoController::class, 'update']);
    Route::delete('/tarefa-padroes/{id}',  [TarefaPadraoController::class, 'destroy']);
});
