<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Estagio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Catálogo de estágios (todos ou de um funil), para selects. */
class EstagioController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(Estagio::query()
            ->when($request->filled('funil_id'), fn ($q) => $q->where('funil_id', $request->integer('funil_id')))
            ->orderBy('funil_id')->orderBy('ordem')->orderBy('id')
            ->get(['id', 'funil_id', 'descricao', 'cor', 'tipo', 'ordem', 'probabilidade_padrao']));
    }
}
