<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Anotacao;
use App\Models\Usuario;
use App\Support\LimitesDeTexto;
use App\Support\Listagem\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnotacaoController extends Controller
{
    public function index(Request $request, Usuario $usuario): JsonResponse
    {
        $this->authorize('view', $usuario);

        $pagina = Anotacao::where('usuario_id', $usuario->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(Paginacao::clamp($request->input('per_page'), 20))
            ->withQueryString();

        return response()->json(Paginacao::resposta($pagina));
    }

    public function store(Request $request, Usuario $usuario): JsonResponse
    {
        $this->authorize('update', $usuario);

        $validated = $request->validate(['descricao' => ['required', 'string', 'max:'.LimitesDeTexto::ANOTACAO]]);

        $anotacao = Anotacao::create(['descricao' => $validated['descricao'], 'usuario_id' => $usuario->id]);

        return response()->json(['message' => 'Anotação salva.', 'data' => $anotacao], 201);
    }

    public function update(Request $request, Anotacao $anotacao): JsonResponse
    {
        $this->authorize('update', $anotacao);

        $anotacao->update($request->validate(['descricao' => ['required', 'string', 'max:'.LimitesDeTexto::ANOTACAO]]));

        return response()->json(['message' => 'Anotação salva.', 'data' => $anotacao]);
    }

    public function destroy(Anotacao $anotacao): JsonResponse
    {
        $this->authorize('delete', $anotacao);

        $anotacao->delete();

        return response()->json(['message' => 'Anotação apagada.']);
    }
}
