<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Projeto;
use App\Models\ProjetoAnotacao;
use App\Support\LimitesDeTexto;
use App\Support\Listagem\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjetoAnotacaoController extends Controller
{
    public function index(Request $request, Projeto $projeto): JsonResponse
    {
        $this->authorize('view', $projeto);

        $pagina = ProjetoAnotacao::where('projeto_id', $projeto->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(Paginacao::clamp($request->input('per_page'), 20))
            ->withQueryString();

        return response()->json(Paginacao::resposta($pagina));
    }

    public function store(Request $request, Projeto $projeto): JsonResponse
    {
        $this->authorize('update', $projeto);

        $validated = $request->validate(['descricao' => ['required', 'string', 'max:'.LimitesDeTexto::ANOTACAO]]);

        $anotacao = ProjetoAnotacao::create(['descricao' => $validated['descricao'], 'projeto_id' => $projeto->id]);

        return response()->json(['message' => 'Anotação salva.', 'data' => $anotacao], 201);
    }

    public function update(Request $request, ProjetoAnotacao $projetoAnotacao): JsonResponse
    {
        $this->authorize('update', $projetoAnotacao);

        $projetoAnotacao->update($request->validate(['descricao' => ['required', 'string', 'max:'.LimitesDeTexto::ANOTACAO]]));

        return response()->json(['message' => 'Anotação salva.', 'data' => $projetoAnotacao]);
    }

    public function destroy(ProjetoAnotacao $projetoAnotacao): JsonResponse
    {
        $this->authorize('delete', $projetoAnotacao);

        $projetoAnotacao->delete();

        return response()->json(['message' => 'Anotação apagada.']);
    }
}
