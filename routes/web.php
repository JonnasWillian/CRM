<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Userarios;

Route::get('/', function () {
    return Inertia::render('Inicial', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', 'tenant'])->name('dashboard');

Route::get('/perfilUsuario', function () {
    return Inertia::render('Usuario/Perfil');
})->middleware(['auth', 'verified', 'tenant'])->name('perfilUsuario');

Route::get('/modelos-tarefa', function () {
    return Inertia::render('ModelosTarefa');
})->middleware(['auth', 'verified', 'tenant'])->name('modelosTarefa');

Route::get('/kanban', function () {
    return Inertia::render('Kanban');
})->middleware(['auth', 'verified', 'tenant'])->name('kanban');

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
