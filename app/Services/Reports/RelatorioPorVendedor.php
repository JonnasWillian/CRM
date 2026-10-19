<?php

namespace App\Services\Reports;

use App\Models\Estagio;
use App\Models\Perda;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Tabela "Por vendedor" e série "Ganhos por mês" da Visão geral.
 * Por vendedor = uma consulta agrupada nos leads + uma nas perdas; nunca
 * uma consulta por pessoa.
 */
class RelatorioPorVendedor
{
    /** @return list<array<string, mixed>> */
    public function porVendedor(Periodo $periodo, ?int $funilId = null): array
    {
        $inicio = $periodo->inicio();
        $fim = $periodo->fim();
        $ganho = Estagio::TIPO_GANHO;
        $aberto = Estagio::TIPO_ABERTO;

        $leads = Usuario::query()
            ->leftJoin('estagios', 'estagios.id', '=', 'usuarios.estagio_id')
            ->when($funilId, fn ($q) => $q->where('usuarios.funil_id', $funilId))
            ->groupBy('usuarios.user_id')
            ->selectRaw('usuarios.user_id,
                SUM(CASE WHEN estagios.tipo = ? THEN 1 ELSE 0 END) as leads_ativos,
                SUM(CASE WHEN usuarios.created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as leads_criados,
                SUM(CASE WHEN estagios.tipo = ? AND usuarios.fechado_em BETWEEN ? AND ? THEN 1 ELSE 0 END) as ganhos,
                COALESCE(SUM(CASE WHEN estagios.tipo = ? AND usuarios.fechado_em BETWEEN ? AND ? THEN COALESCE(usuarios.valor_fechado, usuarios.valor_estimado) END), 0) as valor_ganho,
                COALESCE(SUM(CASE WHEN estagios.tipo = ? AND usuarios.probabilidade IS NOT NULL THEN usuarios.valor_estimado * usuarios.probabilidade / 100 END), 0) as valor_ponderado',
                [$aberto, $inicio, $fim, $ganho, $inicio, $fim, $ganho, $inicio, $fim, $aberto])
            ->get()
            ->keyBy('user_id');

        $perdas = Perda::deLeads()
            ->noPeriodo($inicio, $fim)
            ->join('usuarios', 'usuarios.id', '=', 'perdas.perdivel_id')
            ->when($funilId, fn ($q) => $q->where('usuarios.funil_id', $funilId))
            ->groupBy('usuarios.user_id')
            ->selectRaw('usuarios.user_id, COUNT(*) as perdidos, COALESCE(SUM(perdas.valor), 0) as valor_perdido')
            ->get()
            ->keyBy('user_id');

        // Recorrência por dono do lead: MRR de hoje e o que falta realizar.
        $recorrencia = ResumoDeRecorrencia::contratos(Usuario::query()->when($funilId, fn ($q) => $q->where('usuarios.funil_id', $funilId))->select('usuarios.id'))
            ->groupBy(fn ($p) => $p->usuario?->user_id)
            ->map(fn ($contratos) => ResumoDeRecorrencia::resumir($contratos, $periodo));

        return User::doTenant(app(CurrentTenant::class)->id())
            ->orderBy('name')
            ->get(['id', 'name', 'ativo'])
            ->filter(fn (User $u) => $u->ativo || $leads->has($u->id) || $perdas->has($u->id) || $recorrencia->has($u->id))
            ->map(function (User $u) use ($leads, $perdas, $recorrencia) {
                $rec = $recorrencia->get($u->id);
                $l = $leads->get($u->id);
                $p = $perdas->get($u->id);
                $ganhos = (int) ($l->ganhos ?? 0);
                $perdidos = (int) ($p->perdidos ?? 0);

                return [
                    'user_id' => $u->id,
                    'nome' => $u->name,
                    'ativo' => (bool) $u->ativo,
                    'leads_ativos' => (int) ($l->leads_ativos ?? 0),
                    'leads_criados' => (int) ($l->leads_criados ?? 0),
                    'ganhos' => $ganhos,
                    'valor_ganho' => (float) ($l->valor_ganho ?? 0),
                    'perdidos' => $perdidos,
                    'valor_perdido' => (float) ($p->valor_perdido ?? 0),
                    'taxa_ganho' => ($ganhos + $perdidos) > 0 ? round($ganhos / ($ganhos + $perdidos) * 100, 1) : null,
                    'valor_ponderado' => round((float) ($l->valor_ponderado ?? 0), 2),
                    'mrr' => $rec['mrr'] ?? 0.0,
                    'recorrente_no_periodo' => $rec['no_periodo'] ?? 0.0,
                    'recorrente_a_realizar' => $rec['a_realizar'] ?? 0.0,
                ];
            })->values()->all();
    }

    /** Os últimos 12 meses (o atual por último), por `fechado_em` dos leads ganhos. */
    public function ganhosPorMes(?int $funilId = null, ?int $userId = null): array
    {
        $fim = Carbon::now()->endOfMonth();
        $inicio = Carbon::now()->subMonthsNoOverflow(11)->startOfMonth();

        $porMes = Usuario::query()
            ->whereHas('estagio', fn ($q) => $q->where('tipo', Estagio::TIPO_GANHO))
            ->whereBetween('usuarios.fechado_em', [$inicio, $fim])
            ->when($funilId, fn ($q) => $q->where('usuarios.funil_id', $funilId))
            ->when($userId, fn ($q) => $q->where('usuarios.user_id', $userId))
            ->groupBy('mes')
            ->select(DB::raw("DATE_FORMAT(usuarios.fechado_em, '%Y-%m') as mes"), DB::raw('COUNT(*) as total'), DB::raw('COALESCE(SUM(COALESCE(valor_fechado, valor_estimado)), 0) as valor'))
            ->get()
            ->keyBy('mes');

        $serie = [];
        for ($d = $inicio->copy(); $d->lte($fim); $d->addMonthNoOverflow()) {
            $mes = $d->format('Y-m');
            $serie[] = ['mes' => $mes, 'total' => (int) ($porMes[$mes]->total ?? 0), 'valor' => (float) ($porMes[$mes]->valor ?? 0)];
        }

        return $serie;
    }
}
