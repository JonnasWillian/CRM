<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MetricasRequest;
use App\Services\Reports\LeadMetricsService;
use App\Services\Reports\RelatorioPorVendedor;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;

/** Relatórios › Visão geral (leads.view-all). */
class RelatorioController extends Controller
{
    public function visaoGeral(MetricasRequest $request): JsonResponse
    {
        return response()->json(self::dados($request));
    }

    public static function dados(MetricasRequest $request): array
    {
        $periodo = $request->periodo();
        $agente = $request->agente();
        $funil = $request->funilId();
        $vendedores = app(RelatorioPorVendedor::class);

        return [
            'metricas' => app(LeadMetricsService::class)->resumo(app(CurrentTenant::class)->get(), $agente, $periodo, $funil),
            'por_vendedor' => $vendedores->porVendedor($periodo, $funil),
            'ganhos_por_mes' => $vendedores->ganhosPorMes($funil, $agente?->id),
        ];
    }
}
