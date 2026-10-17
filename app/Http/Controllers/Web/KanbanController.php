<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Estagio;
use App\Models\Funil;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Página Pipeline: a casca vem por props; o quadro em si vem de GET api/kanban (recarregado a cada movimento). */
class KanbanController extends Controller
{
    public function index(Request $request): Response
    {
        $funis = Funil::ordenados()->get(['id', 'nome', 'is_default']);
        $pedido = $request->integer('funil_id') ?: null;
        $atual = ($pedido ? $funis->firstWhere('id', $pedido) : null) ?? $funis->firstWhere('is_default', true) ?? $funis->first();

        return Inertia::render('Kanban', [
            'funis' => $funis,
            'funilAtual' => $atual,
            // Todos os estágios do tenant: o modal de novo lead e o diálogo
            // "mover para outro funil" precisam dos estágios dos outros funis.
            'estagios' => Estagio::query()->orderBy('funil_id')->orderBy('ordem')->orderBy('id')
                ->get(['id', 'funil_id', 'descricao', 'cor', 'tipo', 'ordem', 'probabilidade_padrao']),
            'agentes' => LeadController::agentes($request->user()),
        ]);
    }
}
