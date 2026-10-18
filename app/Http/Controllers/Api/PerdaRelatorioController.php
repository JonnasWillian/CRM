<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Perdas\RelatorioDePerdas;
use App\Services\Reports\Periodo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Relatório "por que perdemos".
 *
 * Gated por `leads.view-all` e não por `configuracoes.manage`: é uma visão
 * agregada da carteira inteira do tenant, que é exatamente o que aquela
 * permission separa. Vendedor não vê o consolidado da empresa — a mesma linha
 * que o spec de RBAC traça para as listagens de lead.
 */
class PerdaRelatorioController extends Controller
{
    public function __construct(private readonly RelatorioDePerdas $relatorio) {}

    public function resumo(Request $request): JsonResponse
    {
        $dados = self::validar($request);

        return response()->json($this->relatorio->resumo(self::periodoDe($dados), $dados['tipo'] ?? null));
    }

    /** Perdas uma a uma (paginado): o que, por quê, quanto, quem e quando. */
    public function detalhe(Request $request): JsonResponse
    {
        $dados = self::validar($request);
        $extra = $request->validate(['user_id' => ['nullable', 'integer'], 'per_page' => ['nullable', 'integer', 'min:1']]);
        $periodo = self::periodoDe($dados);

        $pagina = \App\Models\Perda::query()
            ->noPeriodo($periodo->inicio(), $periodo->fim())
            ->when(($dados['tipo'] ?? null) === 'lead', fn ($q) => $q->deLeads())
            ->when(($dados['tipo'] ?? null) === 'projeto', fn ($q) => $q->deProjetos())
            ->when($extra['user_id'] ?? null, fn ($q, $id) => $q->where('perdas.user_id', $id))
            ->with(['motivo' => fn ($q) => $q->withTrashed(), 'perdivel' => fn ($q) => $q->withTrashed(), 'registradoPor:id,name'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(\App\Support\Listagem\Paginacao::clamp($extra['per_page'] ?? null, 25))
            ->withQueryString();

        $pagina->setCollection($pagina->getCollection()->map(fn (\App\Models\Perda $p) => [
            'id' => $p->id,
            'tipo' => $p->perdivel_type === 'lead' ? 'lead' : 'projeto',
            'nome' => $p->perdivel?->nome,
            'lead_id' => $p->perdivel_type === 'lead' ? $p->perdivel_id : $p->perdivel?->usuario_id,
            'motivo' => $p->motivo?->descricao,
            'valor' => $p->valor === null ? null : (float) $p->valor,
            'vendedor' => $p->registradoPor?->name,
            'observacao' => $p->observacao,
            'data' => $p->created_at?->toIso8601String(),
        ]));

        return response()->json(\App\Support\Listagem\Paginacao::resposta($pagina, null, [...$dados, ...$extra]));
    }

    /** Os parâmetros do relatório (API e página), validados. */
    public static function validar(Request $request): array
    {
        return $request->validate([
            'periodo' => ['nullable', Rule::in([...Periodo::PRESETS, 'custom'])],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
            'tipo' => ['nullable', 'in:lead,projeto'],
        ], [
            'ate.after_or_equal' => 'A data final não pode ser anterior à inicial.',
        ]);
    }

    /**
     * `de`/`ate` soltos (sem `periodo`) continuam valendo. Sem nada, os 90
     * dias padrão deste relatório.
     */
    public static function periodoDe(array $dados): Periodo
    {
        return match (true) {
            isset($dados['periodo']) => Periodo::de($dados['periodo'], $dados['de'] ?? null, $dados['ate'] ?? null),
            isset($dados['de']) || isset($dados['ate']) => Periodo::customizado(
                isset($dados['de']) ? Carbon::parse($dados['de']) : Carbon::now()->subDays(RelatorioDePerdas::DIAS_PADRAO),
                isset($dados['ate']) ? Carbon::parse($dados['ate']) : Carbon::now(),
            ),
            default => RelatorioDePerdas::periodoPadrao(),
        };
    }
}
