<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjetoAnexoRequest;
use App\Models\Projeto;
use App\Models\ProjetoAnexo;
use App\Services\Arquivos\ArquivoService;
use App\Support\Listagem\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjetoAnexoController extends Controller
{
    public function __construct(private readonly ArquivoService $arquivos) {}

    public function index(Request $request, Projeto $projeto): JsonResponse
    {
        $this->authorize('view', $projeto);

        $pagina = ProjetoAnexo::where('projeto_id', $projeto->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(Paginacao::clamp($request->input('per_page'), 20))
            ->withQueryString();

        return response()->json(Paginacao::resposta($pagina));
    }

    public function store(ProjetoAnexoRequest $request, Projeto $projeto): JsonResponse
    {
        // ProjetoAnexoRequest::authorize() já conferiu update no projeto da rota.
        $anexo = $this->arquivos->guardarComRegistro(
            $request->file('arquivo'),
            ArquivoService::pastaDoProjeto($projeto->tenant_id, $projeto->id),
            fn (array $dados) => ProjetoAnexo::create([
                ...$dados,
                'nome' => $request->input('nome') ?: '',
                'projeto_id' => $projeto->id,
            ]),
        );

        return response()->json(['message' => 'Anexo enviado.', 'data' => $anexo], 201);
    }

    public function destroy(ProjetoAnexo $projetoAnexo): JsonResponse
    {
        $this->authorize('delete', $projetoAnexo);

        $projetoAnexo->delete();

        return response()->json(['message' => 'Anexo apagado.']);
    }
}
