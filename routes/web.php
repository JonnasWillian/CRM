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

/*
 * O lead tem URL própria.
 *
 * Antes era `/perfilUsuario` sem identificador, com o id do lead viajando em
 * `sessionStorage`. Na prática isso significava que não dava para mandar o
 * link de um lead para um colega, abrir dois leads em abas para comparar, nem
 * confiar no botão voltar do navegador — e "me manda esse lead" é conversa
 * diária num CRM.
 *
 * O binding implícito resolve o model, e como Usuario usa BelongsToTenant o
 * TenantScope já devolve 404 para um id de outra empresa, sem verificação
 * adicional aqui.
 */
Route::get('/leads/{usuario}', function (App\Models\Usuario $usuario) {
    return Inertia::render('Usuario/Perfil', ['leadId' => $usuario->id]);
})->middleware(['auth', 'verified', 'tenant'])->name('leads.show');

Route::get('/modelos-tarefa', function () {
    return Inertia::render('ModelosTarefa');
})->middleware(['auth', 'verified', 'tenant'])->name('modelosTarefa');

Route::get('/kanban', function () {
    return Inertia::render('Kanban');
})->middleware(['auth', 'verified', 'tenant'])->name('kanban');

Route::get('/configuracoes/funis', function () {
    return Inertia::render('Configuracoes/Funis');
})->middleware(['auth', 'verified', 'tenant', 'can:configuracoes.manage'])->name('configuracoes.funis');

Route::get('/configuracoes/motivos-perda', function () {
    return Inertia::render('Configuracoes/MotivosPerda');
})->middleware(['auth', 'verified', 'tenant', 'can:configuracoes.manage'])->name('configuracoes.motivosPerda');

Route::get('/relatorios/perdas', function () {
    return Inertia::render('Relatorios/Perdas');
})->middleware(['auth', 'verified', 'tenant', 'can:leads.view-all'])->name('relatorios.perdas');

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
