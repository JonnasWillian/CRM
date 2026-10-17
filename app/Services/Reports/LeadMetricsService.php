<?php

namespace App\Services\Reports;

use App\Models\Estagio;
use App\Models\Perda;
use App\Models\Projeto;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Reports\MetricasCache;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Resumo de métricas do dashboard, em cache por (tenant, agente, funil, período).
 *
 * `$agente` nulo = tenant inteiro; preenchido = só a carteira dele. Quem
 * decide é o chamador (MetricasRequest::agente), não o serviço.
 *
 * Estado × fluxo: sete métricas são retrato do momento (não recebem
 * período); as de fluxo (criados, ganhos, perdidos) leem a janela. O
 * forecast é estado com janela à frente (previsão por mês), não recortado
 * pelo período — que olha para trás.
 */
class LeadMetricsService
{
    public function resumo(Tenant $tenant, ?User $agente, Periodo $periodo, ?int $funilId = null): array
    {
        // Guarda contra vazamento: a consulta é filtrada pelo tenant ATIVO
        // (TenantScope); gravar o resultado sob a chave de outro tenant
        // serviria os dados de B para quem pediu A.
        if (app(CurrentTenant::class)->id() !== $tenant->id) {
            throw new RuntimeException('Tenant das métricas difere do tenant ativo.');
        }

        $chave = MetricasCache::chave($tenant->id, $agente?->id, $funilId, $periodo->hash());

        return Cache::remember($chave, (int) config('reports.metricas_ttl', 300), fn () => $this->calcular($agente, $periodo, $funilId));
    }

    private function calcular(?User $agente, Periodo $periodo, ?int $funilId): array
    {
        $leads = fn (): Builder => Usuario::query()
            ->when($agente, fn (Builder $q) => $q->where('usuarios.user_id', $agente->id))
            ->when($funilId, fn (Builder $q) => $q->where('usuarios.funil_id', $funilId));
        $ids = fn (): Builder => $leads()->select('usuarios.id');

        $inicio = $periodo->inicio();
        $fim = $periodo->fim();

        // ── Estado ──
        $leadsAtivos = $leads()->whereHas('estagio', fn ($q) => $q->aberto())->count();
        $leadsFechados = $leads()->whereHas('estagio', fn ($q) => $q->fechado())->count();
        $totalLeads = $leads()->count();
        $leadsComProjeto = Projeto::whereIn('usuario_id', $ids())->distinct('usuario_id')->count('usuario_id');
        $valorProjetosAbertos = (float) Projeto::whereIn('usuario_id', $ids())->whereHas('status', fn ($q) => $q->open())->sum('preco');
        $semContato = $leads()->whereHas('estagio', fn ($q) => $q->aberto())
            ->where('usuarios.ultimo_contato_em', '<', now()->subDays((int) config('leads.dias_estagnado', 14)))
            ->count();

        $leadsPorEstagio = $leads()
            ->select('usuarios.estagio_id', DB::raw('count(*) as total'))
            ->with('estagio')
            ->groupBy('usuarios.estagio_id')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->estagio_id,
                'descricao' => $row->estagio->descricao ?? 'Sem estágio',
                'cor' => $row->estagio->cor ?? null,
                'tipo' => $row->estagio->tipo ?? null,
                'total' => (int) $row->total,
            ])->values();

        // ── Fluxo (no período) ──
        $leadsCriados = $leads()->whereBetween('usuarios.created_at', [$inicio, $fim])->count();

        $ganhos = $leads()
            ->whereHas('estagio', fn ($q) => $q->where('tipo', Estagio::TIPO_GANHO))
            ->whereBetween('usuarios.fechado_em', [$inicio, $fim])
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(COALESCE(valor_fechado, valor_estimado)), 0) as valor')
            ->first();

        $perdidos = Perda::deLeads()
            ->whereIn('perdivel_id', $ids())
            ->whereBetween('created_at', [$inicio, $fim])
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(valor), 0) as valor')
            ->first();

        $totalGanhos = (int) $ganhos->total;
        $totalPerdidos = (int) $perdidos->total;
        $valorGanho = (float) $ganhos->valor;

        $valorFechadoProjetos = (float) Projeto::whereIn('usuario_id', $ids())
            ->whereHas('status', fn ($q) => $q->where('is_won', true))
            ->whereBetween('fechado_em', [$inicio, $fim])
            ->sum('preco');

        // ── Forecast (leads abertos, janela à frente) ──
        $abertos = fn (): Builder => $leads()->whereHas('estagio', fn ($q) => $q->aberto());
        $hoje = Carbon::today();

        $agregado = $abertos()->selectRaw(
            'COALESCE(SUM(valor_estimado), 0) as valor_estimado,
             COALESCE(SUM(CASE WHEN probabilidade IS NOT NULL THEN valor_estimado * probabilidade / 100 END), 0) as valor_ponderado,
             SUM(CASE WHEN valor_estimado IS NOT NULL AND probabilidade IS NULL THEN 1 ELSE 0 END) as sem_probabilidade,
             SUM(CASE WHEN previsao_fechamento >= ? THEN 1 ELSE 0 END) as leads_com_previsao,
             SUM(CASE WHEN previsao_fechamento < ? THEN 1 ELSE 0 END) as previsao_vencida',
            [$hoje->toDateString(), $hoje->toDateString()],
        )->first();

        $fimJanela = $hoje->copy()->addMonthsNoOverflow(2)->endOfMonth();
        $porMesBanco = $abertos()
            ->whereBetween('usuarios.previsao_fechamento', [$hoje->toDateString(), $fimJanela->toDateString()])
            ->selectRaw("DATE_FORMAT(previsao_fechamento, '%Y-%m') as mes,
                COUNT(*) as total,
                COALESCE(SUM(valor_estimado), 0) as valor_estimado,
                COALESCE(SUM(CASE WHEN probabilidade IS NOT NULL THEN valor_estimado * probabilidade / 100 END), 0) as valor_ponderado")
            ->groupBy('mes')
            ->get()
            ->keyBy('mes');

        $porMes = [];
        for ($i = 0; $i < 3; $i++) {
            $mes = $hoje->copy()->addMonthsNoOverflow($i)->format('Y-m');
            $linha = $porMesBanco->get($mes);
            $porMes[] = [
                'mes' => $mes,
                'total' => (int) ($linha->total ?? 0),
                'valor_estimado' => (float) ($linha->valor_estimado ?? 0),
                'valor_ponderado' => (float) ($linha->valor_ponderado ?? 0),
            ];
        }

        $taxaConversao = $totalLeads > 0 ? round($leadsComProjeto / $totalLeads * 100, 1) : 0.0;

        return [
            // Chaves que o Dashboard atual lê (mantidas até a migração da tela).
            'leads_ativos' => $leadsAtivos,
            'leads_arquivados' => $leadsFechados,
            'leads_30_dias' => $leadsCriados,
            'valor_projetos_abertos' => $valorProjetosAbertos,
            'valor_fechado_mes' => $valorFechadoProjetos,
            'leads_por_estagio' => $leadsPorEstagio,
            'taxa_conversao' => $taxaConversao,
            'total_com_projeto' => $leadsComProjeto,
            'total_leads' => $totalLeads,

            'periodo' => $periodo->toArray(),
            'estado' => [
                'leads_ativos' => $leadsAtivos,
                'leads_fechados' => $leadsFechados,
                'total_leads' => $totalLeads,
                'leads_por_estagio' => $leadsPorEstagio,
                'sem_contato_14d' => $semContato,
            ],
            'fluxo' => [
                'leads_criados' => $leadsCriados,
                'ganhos' => ['total' => $totalGanhos, 'valor' => $valorGanho],
                'perdidos' => ['total' => $totalPerdidos, 'valor' => (float) $perdidos->valor],
                'taxa_ganho' => ($totalGanhos + $totalPerdidos) > 0 ? round($totalGanhos / ($totalGanhos + $totalPerdidos) * 100, 1) : null,
                'ticket_medio' => $totalGanhos > 0 ? round($valorGanho / $totalGanhos, 2) : null,
                'valor_fechado_projetos' => $valorFechadoProjetos,
            ],
            'forecast' => [
                'valor_estimado_aberto' => (float) $agregado->valor_estimado,
                'valor_ponderado' => (float) $agregado->valor_ponderado,
                'sem_probabilidade' => (int) $agregado->sem_probabilidade,
                'leads_com_previsao' => (int) $agregado->leads_com_previsao,
                'previsao_vencida' => (int) $agregado->previsao_vencida,
                'valor_projetos_abertos' => $valorProjetosAbertos,
                'por_mes' => $porMes,
            ],
        ];
    }
}
