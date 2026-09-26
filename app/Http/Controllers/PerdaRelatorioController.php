<?php

namespace App\Http\Controllers;

use App\Services\Perdas\RelatorioDePerdas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
        $dados = $request->validate([
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
            'tipo' => ['nullable', 'in:lead,projeto'],
        ], [
            'ate.after_or_equal' => 'A data final não pode ser anterior à inicial.',
        ]);

        return response()->json($this->relatorio->resumo(
            isset($dados['de']) ? Carbon::parse($dados['de']) : null,
            isset($dados['ate']) ? Carbon::parse($dados['ate']) : null,
            $dados['tipo'] ?? null,
        ));
    }
}
