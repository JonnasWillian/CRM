<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\LeadController as LeadApi;
use App\Http\Controllers\Controller;
use App\Models\Funil;
use App\Models\Statu;
use App\Models\Usuario;
use Inertia\Inertia;
use Inertia\Response;

/** Páginas Inertia de leads. A grade (`index`) chega na Fase 3b. */
class LeadController extends Controller
{
    /**
     * Perfil do lead. A autorização está no middleware da rota
     * (`->can('view', 'usuario')`), que roda depois do binding.
     */
    public function show(Usuario $usuario): Response
    {
        return Inertia::render('Usuario/Perfil', [
            'lead' => LeadApi::comResumo($usuario),
            'funis' => Funil::ordenados()->with(['estagios' => fn ($q) => $q->orderBy('ordem')->orderBy('id')])->get(['id', 'nome', 'is_default']),
            'status' => Statu::orderBy('ordem')->orderBy('id')->get(['id', 'descricao', 'is_won', 'is_lost']),
        ]);
    }
}
