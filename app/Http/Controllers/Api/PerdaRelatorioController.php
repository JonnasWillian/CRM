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
