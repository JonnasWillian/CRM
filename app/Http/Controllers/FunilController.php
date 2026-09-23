<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstagioRequest;
use App\Http\Requests\FunilRequest;
use App\Models\Estagio;
use App\Models\Funil;
use App\Services\Funis\EstagioService;
use App\Services\Funis\FunilService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Configuração de funis e seus estágios.
 *
 * Todas as rotas daqui exigem `configuracoes.manage`, exceto `index`, que é
 * consumida também pelo Kanban e pelo cadastro de lead — telas que qualquer
 * agente usa e que precisam saber quais funis existem.
 *
 * Os models chegam resolvidos por route model binding. Como `Funil` e `Estagio`
 * usam BelongsToTenant, o TenantScope já faz o binding devolver 404 para um id
 * de outro tenant, sem verificação adicional no controller.
 */
class FunilController extends Controller
{
    public function __construct(
        private readonly FunilService $funis,
        private readonly EstagioService $estagios,
    ) {}

    public function index(): JsonResponse
    {
        $funis = Funil::ordenados()
            // withTrashed: um estágio arquivado continua aparecendo no Kanban
            // enquanto segura leads. Escondê-lo da tela de configuração deixaria
            // o administrador olhando para uma coluna do quadro que ele não acha
            // em lugar nenhum — e sem como restaurá-la.
            ->with(['estagios' => fn ($q) => $q->withTrashed()->orderBy('ordem')->orderBy('id')])
            ->get()
            ->map(fn (Funil $funil) => $this->serializar($funil));

        return response()->json($funis);
    }

    public function store(FunilRequest $request): JsonResponse
    {
        $funil = $this->funis->criar($request->validated());

        return response()->json($this->serializar($funil->load('estagios')), 201);
    }

    public function update(FunilRequest $request, Funil $funil): JsonResponse
    {
        $this->funis->atualizar($funil, $request->validated());

        return response()->json($this->serializar($funil->load('estagios')));
    }

    public function destroy(Funil $funil): JsonResponse
    {
        $this->funis->arquivar($funil);

        return response()->json(['message' => 'Funil arquivado.']);
    }

    public function definirPadrao(Funil $funil): JsonResponse
    {
        $this->funis->definirPadrao($funil);

        return response()->json(['message' => 'Funil padrão atualizado.']);
    }

    public function reordenar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $this->funis->reordenar($dados['ids']);

        return response()->json(['message' => 'Ordem dos funis salva.']);
    }

    public function storeEstagio(EstagioRequest $request, Funil $funil): JsonResponse
    {
        $estagio = $this->estagios->criar($funil, $request->validated());

        return response()->json($this->serializarEstagio($estagio), 201);
    }

    public function updateEstagio(EstagioRequest $request, Estagio $estagio): JsonResponse
    {
        $this->estagios->atualizar($estagio, $request->validated());

        return response()->json($this->serializarEstagio($estagio));
    }

    public function restaurarEstagio(Estagio $estagio): JsonResponse
    {
        $this->estagios->restaurar($estagio);

        return response()->json(['message' => 'Estágio restaurado.']);
    }

    public function destroyEstagio(Estagio $estagio): JsonResponse
    {
        $this->estagios->arquivar($estagio);

        return response()->json(['message' => 'Estágio arquivado.']);
    }

    public function reordenarEstagios(Request $request, Funil $funil): JsonResponse
    {
        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $this->estagios->reordenar($funil, $dados['ids']);

        return response()->json(['message' => 'Ordem dos estágios salva.']);
    }

    private function serializar(Funil $funil): array
    {
        return [
            'id' => $funil->id,
            'nome' => $funil->nome,
            'descricao' => $funil->descricao,
            'ordem' => $funil->ordem,
            'is_default' => $funil->is_default,
            'total_leads' => $funil->leads()->count(),
            'estagios' => $funil->estagios->map(fn (Estagio $e) => $this->serializarEstagio($e))->values(),
        ];
    }

    private function serializarEstagio(Estagio $estagio): array
    {
        return [
            'id' => $estagio->id,
            'funil_id' => $estagio->funil_id,
            'descricao' => $estagio->descricao,
            'ordem' => $estagio->ordem,
            'tipo' => $estagio->tipo,
            'cor' => $estagio->cor,
            'arquivada' => $estagio->trashed(),
        ];
    }
}
