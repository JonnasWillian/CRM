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
 * ── Correção da justificativa anterior ──
 *
 * Este comentário dizia que o TenantScope bastava, "sem verificação adicional
 * aqui". Era falso, e pior do que falso: aparentava conferência. O TenantScope
 * resolve o lead de OUTRA empresa; não resolve nada contra o colega da MESMA
 * empresa. Sem o `can` abaixo, um vendedor pedindo /leads/{id de um colega}
 * recebia 200 e /leads/999999 recebia 404 — a diferença enumera os ids do
 * tenant inteiro sem nem precisar ler o corpo da resposta. Era a nona porta de
 * um plano que fechou oito.
 *
 * `->can('view', 'usuario')` e não Gate::authorize() dentro do closure: a
 * autorização fica na mesma linha em que já estão `auth`, `verified` e
 * `tenant`, que é onde quem lê o arquivo de rotas procura por ela — um closure
 * de duas linhas é exatamente onde ninguém vai olhar. O middleware `can` roda
 * depois de SubstituteBindings (a prioridade padrão do framework põe
 * Authorize logo após ele), então recebe o model já resolvido. A policy
 * responde com denyAsNotFound(), então a negativa é 404, igual à do id que
 * não existe.
 */
Route::get('/leads/{usuario}', function (App\Models\Usuario $usuario) {
    return Inertia::render('Usuario/Perfil', ['leadId' => $usuario->id]);
})->middleware(['auth', 'verified', 'tenant'])->can('view', 'usuario')->name('leads.show');

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
