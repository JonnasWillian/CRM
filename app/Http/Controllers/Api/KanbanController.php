<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\KanbanRequest;
use App\Models\Estagio;
use App\Models\Funil;
use App\Queries\Kanban\QuadroDoKanban;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KanbanController extends Controller
{
    /** O quadro de um funil (o padrão do tenant se não vier `funil_id`). */
    public function index(KanbanRequest $request, QuadroDoKanban $quadro): JsonResponse
    {
        // Com um funil_id que não existe neste tenant, 404 — devolver o padrão
        // calado faria o usuário ver um quadro que não é o que ele pediu.
        $funil = $request->filled('funil_id') ? Funil::findOrFail($request->integer('funil_id')) : Funil::padrao();
        $funis = Funil::ordenados()->get(['id', 'nome', 'is_default']);

        if ($funil === null) {
            return response()->json(['funil' => null, 'funis' => $funis, 'filtros' => $request->filtros(), 'totais' => null, 'estagios' => []]);
        }

        return response()->json(['funis' => $funis, ...$quadro($request->user(), $funil, $request->filtros())]);
    }

    /** "Carregar mais" de uma coluna: `{data, meta, sort, filtros}` só daquele estágio. */
    public function coluna(KanbanRequest $request, QuadroDoKanban $quadro, Estagio $estagio): JsonResponse
    {
        return response()->json($quadro->coluna($request->user(), $estagio, $request->filtros(), $request->integer('page') ?: null));
    }

    /** Coluna padrão para novos leads: preferência do próprio usuário. */
    public function settings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'default_estagio_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('estagios', 'id')
                    ->where('tenant_id', app(CurrentTenant::class)->id())
                    ->whereNull('deleted_at'),
            ],
        ]);

        $request->user()->update(['kanban_default_estagio_id' => $validated['default_estagio_id']]);

        return response()->json(['message' => 'Coluna padrão atualizada.']);
    }
}
