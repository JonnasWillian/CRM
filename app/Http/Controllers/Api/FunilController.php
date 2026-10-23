<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EstagioRequest;
use App\Http\Requests\FunilRequest;
use App\Models\Estagio;
use App\Models\Funil;
use App\Services\Funis\EstagioService;
use App\Services\Funis\FunilService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        return response()->json(self::todos());
    }

    /**
     * Os funis com estágios, para a API e para as props da página de
     * configuração. `incluirArquivados` traz também os funis na lixeira
     * (seção "Arquivados" da tela, com Restaurar).
     *
     * @return list<array<string, mixed>>
     */
    public static function todos(bool $incluirArquivados = false): array
    {
        return Funil::ordenados()
            ->when($incluirArquivados, fn ($q) => $q->withTrashed())
            // Contagem de leads por funil numa consulta só, não uma por funil.
            ->withCount('leads')
            // withTrashed: um estágio arquivado continua aparecendo no Kanban
            // enquanto segura leads. Escondê-lo da tela de configuração deixaria
            // o administrador olhando para uma coluna do quadro que ele não acha
            // em lugar nenhum — e sem como restaurá-la.
            ->with(['estagios' => fn ($q) => $q->withTrashed()->orderBy('ordem')->orderBy('id')])
            ->get()
            ->map(fn (Funil $funil) => self::serializar($funil))
            ->all();
    }

    public function store(FunilRequest $request): JsonResponse
    {
        app(\App\Services\Assinaturas\Assinaturas::class)->verificarLimite(app(\App\Support\Tenancy\CurrentTenant::class)->id(), 'funis');
        $funil = $this->funis->criar($request->validated());

        return response()->json(self::serializar($funil->load('estagios')), 201);
    }

    public function update(FunilRequest $request, Funil $funil): JsonResponse
    {
        $this->funis->atualizar($funil, $request->validated());

        return response()->json(self::serializar($funil->load('estagios')));
    }

    public function destroy(Funil $funil): JsonResponse
    {
        $this->funis->arquivar($funil);

        return response()->json(['message' => 'Funil arquivado.']);
    }

    public function restaurar(Funil $funil): JsonResponse
    {
        $this->funis->restaurar($funil);

        return response()->json(self::serializar(
            $funil->fresh()->load(['estagios' => fn ($q) => $q->withTrashed()->orderBy('ordem')->orderBy('id')])
        ));
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

        return response()->json(self::serializarEstagio($estagio), 201);
    }

    public function updateEstagio(EstagioRequest $request, Estagio $estagio): JsonResponse
    {
        $this->estagios->atualizar($estagio, $request->validated());

        return response()->json(self::serializarEstagio($estagio));
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

    public static function serializar(Funil $funil): array
    {
        return [
            'id' => $funil->id,
            'nome' => $funil->nome,
            'descricao' => $funil->descricao,
            'ordem' => $funil->ordem,
            'is_default' => $funil->is_default,
            'arquivado' => $funil->trashed(),
            'total_leads' => $funil->leads_count ?? $funil->loadCount('leads')->leads_count,
            'estagios' => $funil->estagios->map(fn (Estagio $e) => self::serializarEstagio($e))->values(),
        ];
    }

    public static function serializarEstagio(Estagio $estagio): array
    {
        return [
            'id' => $estagio->id,
            'funil_id' => $estagio->funil_id,
            'descricao' => $estagio->descricao,
            'ordem' => $estagio->ordem,
            'tipo' => $estagio->tipo,
            'cor' => $estagio->cor,
            'probabilidade_padrao' => $estagio->probabilidade_padrao,
            'arquivada' => $estagio->trashed(),
        ];
    }
}
