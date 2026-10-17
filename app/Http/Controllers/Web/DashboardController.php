<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\MetricasRequest;
use App\Models\Funil;
use App\Queries\Tarefas\ResumoDeTarefas;
use App\Services\Reports\LeadMetricsService;
use App\Services\Reports\Periodo;
use App\Support\Tenancy\CurrentTenant;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard: período, vendedor e funil na URL; métricas e resumo de tarefas
 * como props diferidas (a casca renderiza na hora, os números chegam no
 * segundo passo com esqueleto). Trocar o período é um `router.get` com
 * `only: ['metricas', 'tarefas', 'periodo']`.
 */
class DashboardController extends Controller
{
    public function index(MetricasRequest $request, CurrentTenant $tenant, LeadMetricsService $metricas, ResumoDeTarefas $tarefas): Response
    {
        $periodo = $request->periodo();
        $agente = $request->agente();
        $funilId = $request->funilId();

        return Inertia::render('Dashboard', [
            'periodo' => [
                'periodo' => $periodo->chave(),
                'de' => $periodo->chave() === 'custom' ? $periodo->inicio()->toDateString() : null,
                'ate' => $periodo->chave() === 'custom' ? $periodo->fim()->toDateString() : null,
                'user_id' => $request->user()->can('leads.view-all') ? $agente?->id : null,
                'funil_id' => $funilId,
            ],
            'presets' => collect([...Periodo::PRESETS, 'custom'])->map(fn ($p) => ['chave' => $p, 'rotulo' => $p === 'custom' ? 'Personalizado' : Periodo::preset($p)->rotulo()])->all(),
            'funis' => Funil::ordenados()->get(['id', 'nome', 'is_default']),
            'agentes' => LeadController::agentes($request->user()),
            'metricas' => Inertia::defer(fn () => $metricas->resumo($tenant->get(), $agente, $periodo, $funilId)),
            'tarefas' => Inertia::defer(fn () => $tarefas($request->user())),
        ]);
    }
}
