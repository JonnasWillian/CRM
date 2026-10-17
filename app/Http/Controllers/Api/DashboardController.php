<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\MetricasRequest;
use App\Services\Reports\LeadMetricsService;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(private readonly LeadMetricsService $metricas) {}

    public function metricas(MetricasRequest $request, CurrentTenant $tenant): JsonResponse
    {
        return response()->json($this->metricas->resumo(
            $tenant->get(),
            $request->agente(),
            $request->periodo(),
            $request->funilId(),
        ));
    }
}
