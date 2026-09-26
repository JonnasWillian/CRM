<?php

namespace App\Http\Controllers;

use App\Http\Requests\MotivoPerdaRequest;
use App\Models\MotivoPerda;
use App\Services\Perdas\MotivoPerdaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catálogo de motivos de perda.
 *
 * `index` é aberta a qualquer agente: o modal que pede o motivo ao arrastar um
 * card precisa da lista, e quem arrasta não necessariamente administra a
 * configuração. A escrita exige `configuracoes.manage`.
 */
class MotivoPerdaController extends Controller
{
    public function __construct(private readonly MotivoPerdaService $motivos) {}

    /**
     * Sem arquivados por padrão: esta lista alimenta o seletor de quem está
     * registrando uma perda AGORA, e um motivo aposentado não é escolha válida.
     * `?incluir_arquivados=1` é para a tela de configuração, que precisa
     * mostrá-los para poder restaurá-los.
     */
    public function index(Request $request): JsonResponse
    {
        $incluirArquivados = $request->boolean('incluir_arquivados')
            && ($request->user()?->can('configuracoes.manage') ?? false);

        $motivos = MotivoPerda::query()
            ->when($incluirArquivados, fn ($q) => $q->withTrashed())
            ->ordenados()
            ->get()
            ->map(fn (MotivoPerda $m) => $this->serializar($m));

        return response()->json($motivos);
    }

    public function store(MotivoPerdaRequest $request): JsonResponse
    {
        $motivo = $this->motivos->criar($request->validated());

        return response()->json($this->serializar($motivo), 201);
    }

    public function update(MotivoPerdaRequest $request, MotivoPerda $motivo): JsonResponse
    {
        $this->motivos->atualizar($motivo, $request->validated());

        return response()->json($this->serializar($motivo));
    }

    public function destroy(MotivoPerda $motivo): JsonResponse
    {
        $this->motivos->arquivar($motivo);

        return response()->json(['message' => 'Motivo arquivado.']);
    }

    public function restaurar(MotivoPerda $motivo): JsonResponse
    {
        $this->motivos->restaurar($motivo);

        return response()->json(['message' => 'Motivo restaurado.']);
    }

    public function reordenar(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $this->motivos->reordenar($dados['ids']);

        return response()->json(['message' => 'Ordem dos motivos salva.']);
    }

    private function serializar(MotivoPerda $motivo): array
    {
        return [
            'id' => $motivo->id,
            'descricao' => $motivo->descricao,
            'ordem' => $motivo->ordem,
            'arquivado' => $motivo->trashed(),
            // Quantas perdas já citam este motivo — é o que diz ao
            // administrador se renomeá-lo vai mexer no histórico do relatório.
            'total_perdas' => $motivo->perdas()->count(),
        ];
    }
}
