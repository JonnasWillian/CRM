<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListagemDeTarefasRequest;
use App\Queries\Tarefas\ListagemDeTarefas;
use App\Support\Listagem\Paginacao;
use Inertia\Inertia;
use Inertia\Response;

/** Página Tarefas: lista global por escopo de vencimento. */
class TarefaController extends Controller
{
    public function index(ListagemDeTarefasRequest $request, ListagemDeTarefas $listagem): Response
    {
        $ordenacao = $request->ordenacao();
        $filtros = ['escopo' => ListagemDeTarefas::ESCOPO_PADRAO, ...$request->filtrosValidados()];

        return Inertia::render('Tarefas/Index', [
            'tarefas' => Paginacao::resposta($listagem($request->user(), $filtros, $ordenacao, $request->porPagina()), $ordenacao, $filtros),
            'escopo' => $filtros['escopo'],
            'agentes' => LeadController::agentes($request->user()),
        ]);
    }
}
