<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Userarios;
use App\Http\Controllers\arquivo;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/pegarUsuarios', [Userarios::class, 'view']);
Route::post('/tags', [Userarios::class, 'tags']);
Route::post('/usuarios', [Userarios::class, 'create']);
Route::get('/usuarioPerfil/{id}', [Userarios::class, 'viewUsuario']);
Route::put('/usuarios/{id}', [Userarios::class, 'update']);
Route::delete('/usuarios/{usuario}', [Userarios::class, 'destroy']);

Route::get('/anotacao/{id}', [Userarios::class, 'viewAnotacao']);
Route::post('/anotacao', [Userarios::class, 'createAnotacao']);
Route::put('/anotacao/{id}', [Userarios::class, 'updateAnotacao']);
Route::delete('/anotacao/{id}', [Userarios::class, 'destroyAnotacao']);

Route::apiResource('arquivos', arquivo::class);
Route::post('buscarArquivo', [arquivo::class, 'index']);