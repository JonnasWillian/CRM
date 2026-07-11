<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Userarios;
use App\Http\Controllers\ProjetoController;
use App\Http\Controllers\arquivo;
use App\Http\Controllers\TarefaController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/tags', [Userarios::class, 'tags']);
Route::post('/status', [ProjetoController::class, 'getStatus']);

// Leads (usuarios)
Route::post('/pegarUsuarios', [Userarios::class, 'view']);
Route::post('/usuarios', [Userarios::class, 'create']);
Route::get('/usuarioPerfil/{id}', [Userarios::class, 'viewUsuario']);
Route::put('/usuarios/{id}', [Userarios::class, 'update']);
Route::delete('/usuarios/{usuario}', [Userarios::class, 'destroy']);
Route::get('/timeline/{id}', [Userarios::class, 'timeline']);
Route::post('/metricas', [Userarios::class, 'metricas']);

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
