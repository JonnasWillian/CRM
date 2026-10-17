<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ArquivoRequest;
use App\Models\arquivo as ArquivoModel;
use App\Models\Usuario;
use App\Services\Arquivos\ArquivoService;
use App\Support\Listagem\Paginacao;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Documentos do lead. O lead vem da rota; a policy roda no binding. */
class ArquivoController extends Controller
{
    public function __construct(private readonly ArquivoService $arquivos) {}

    public function index(Request $request, Usuario $usuario): JsonResponse
    {
        $this->authorize('view', $usuario);

        $pagina = ArquivoModel::where('usuario_id', $usuario->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(Paginacao::clamp($request->input('per_page'), 20))
            ->withQueryString();

        return response()->json(Paginacao::resposta($pagina));
    }

    public function store(ArquivoRequest $request, Usuario $usuario): JsonResponse
    {
        // ArquivoRequest::authorize() já conferiu update no lead da rota.
        $salvo = $this->arquivos->guardarComRegistro(
            $request->file('arquivo'),
            ArquivoService::pastaDoLead($usuario->tenant_id, $usuario->id),
            fn (array $dados) => ArquivoModel::create([
                ...$dados,
                'nome' => $request->input('nome') ?: '',
                'usuario_id' => $usuario->id,
            ]),
        );

        return response()->json(['message' => 'Documento enviado.', 'data' => $salvo], 201);
    }

    public function destroy(ArquivoModel $arquivo): JsonResponse
    {
        $this->authorize('delete', $arquivo);

        $arquivo->delete();

        return response()->json(['message' => 'Documento apagado.']);
    }
}
