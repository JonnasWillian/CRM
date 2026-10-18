<?php

use App\Http\Controllers\DownloadDeArquivoController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Inicial', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Web\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/leads', [\App\Http\Controllers\Web\LeadController::class, 'index'])->name('leads.index');
    Route::post('/leads', [\App\Http\Controllers\Web\LeadController::class, 'store'])->name('leads.store');
    Route::get('/leads/importar', [\App\Http\Controllers\Web\LeadController::class, 'importar'])->middleware('can:leads.view-all')->name('leads.importar');
    Route::get('/tarefas', [\App\Http\Controllers\Web\TarefaController::class, 'index'])->name('tarefas.index');
});

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
 * autorização fica na mesma linha em que já estão `auth` e
 * `tenant`, que é onde quem lê o arquivo de rotas procura por ela — um closure
 * de duas linhas é exatamente onde ninguém vai olhar. O middleware `can` roda
 * depois de SubstituteBindings (a prioridade padrão do framework põe
 * Authorize logo após ele), então recebe o model já resolvido. A policy
 * responde com denyAsNotFound(), então a negativa é 404, igual à do id que
 * não existe.
 */
Route::get('/leads/{usuario}', [\App\Http\Controllers\Web\LeadController::class, 'show'])
    ->middleware(['auth', 'tenant'])->withTrashed()->can('view', 'usuario')->name('leads.show');

Route::get('/lixeira', [\App\Http\Controllers\Web\LixeiraController::class, 'index'])->middleware(['auth', 'tenant'])->name('lixeira');

/*
 * Download de anexos. Ver DownloadDeArquivoController: é a única saída do
 * conteúdo, e passa por auth, tenant e policy. O disco `public` não guarda
 * mais anexo nenhum.
 */
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/arquivos/{arquivo}/download', [DownloadDeArquivoController::class, 'lead'])
        ->name('arquivos.download');
    Route::get('/projeto-anexos/{projetoAnexo}/download', [DownloadDeArquivoController::class, 'projeto'])
        ->name('projetoAnexos.download');
});

Route::get('/kanban', [\App\Http\Controllers\Web\KanbanController::class, 'index'])->middleware(['auth', 'tenant'])->name('kanban');

Route::middleware(['auth', 'tenant'])->prefix('configuracoes')->name('configuracoes.')->group(function () {
    Route::get('/funis', [\App\Http\Controllers\Web\ConfiguracoesController::class, 'funis'])->middleware('can:configuracoes.manage')->name('funis');
    Route::get('/motivos-perda', [\App\Http\Controllers\Web\ConfiguracoesController::class, 'motivosPerda'])->middleware('can:configuracoes.manage')->name('motivosPerda');
    Route::get('/equipe', [\App\Http\Controllers\Web\EquipeController::class, 'index'])->middleware('can:agentes.manage')->name('equipe');
    // Do próprio agente, sem permissão extra.
    Route::get('/modelos-tarefa', [\App\Http\Controllers\Web\ConfiguracoesController::class, 'modelosTarefa'])->name('modelosTarefa');
});

Route::middleware(['auth', 'tenant', 'can:leads.view-all'])->group(function () {
    Route::get('/relatorios', [\App\Http\Controllers\Web\RelatorioController::class, 'index'])->name('relatorios.index');
    Route::get('/relatorios/perdas', [\App\Http\Controllers\Web\RelatorioController::class, 'perdas'])->name('relatorios.perdas');
});

Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Aceitar convite: visitante; o token (hash no banco) é a credencial.
Route::middleware('guest')->group(function () {
    Route::get('/convites/{token}', [\App\Http\Controllers\Web\ConviteController::class, 'show'])->where('token', '[A-Za-z0-9]{20,100}')->name('convites.show');
    Route::post('/convites/{token}', [\App\Http\Controllers\Web\ConviteController::class, 'store'])->where('token', '[A-Za-z0-9]{20,100}')->middleware('throttle:6,1')->name('convites.store');
});

require __DIR__.'/auth.php';
