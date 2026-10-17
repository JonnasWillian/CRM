<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjetoRequest;
use App\Models\Projeto;
use App\Models\Usuario;
use App\Services\Perdas\AplicarTransicao;
use App\Support\Listagem\Paginacao;
use App\Support\Perdas\RegrasDePerda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjetoController extends Controller
{
    public function index(Request $request, Usuario $usuario): JsonResponse
    {
        $this->authorize('view', $usuario);

        $pagina = Projeto::where('usuario_id', $usuario->id)
            ->with('status')
            ->withCount(['anotacoes', 'anexos'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(Paginacao::clamp($request->input('per_page'), 20))
            ->withQueryString();

        return response()->json(Paginacao::resposta($pagina));
    }

    public function store(ProjetoRequest $request, Usuario $usuario): JsonResponse
    {
        // ProjetoRequest::authorize() já conferiu update no lead da rota. Um
        // projeto pode nascer já perdido — o status é escolhido no formulário.
        $projeto = new Projeto;
        app(AplicarTransicao::class)($projeto, [...$request->validated(), 'usuario_id' => $usuario->id], RegrasDePerda::extrair($request));

        return response()->json(['message' => 'Projeto salvo.', 'data' => $projeto->fresh('status')], 201);
    }

    public function show(Projeto $projeto): JsonResponse
    {
        $this->authorize('view', $projeto);

        return response()->json($projeto->load('status'));
    }

    public function update(ProjetoRequest $request, Projeto $projeto): JsonResponse
    {
        // Instância autorizada em ProjetoRequest::authorize().
        app(AplicarTransicao::class)($projeto, $request->validated(), RegrasDePerda::extrair($request));

        return response()->json(['message' => 'Projeto salvo.', 'data' => $projeto->fresh('status')]);
    }

    public function destroy(Projeto $projeto): JsonResponse
    {
        $this->authorize('delete', $projeto);

        $projeto->delete();

        return response()->json(['message' => 'Projeto movido para a lixeira.']);
    }

    public function restaurar(Projeto $projeto): JsonResponse
    {
        $this->authorize('restore', $projeto);

        if (! $projeto->trashed()) {
            return response()->json(['message' => 'Este projeto não está na lixeira.'], 409);
        }

        $projeto->restore();

        return response()->json(['message' => 'Projeto restaurado.']);
    }
}
