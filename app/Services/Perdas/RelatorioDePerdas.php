<?php

namespace App\Services\Perdas;

use App\Models\Perda;
use App\Services\Reports\Periodo;
use App\Support\Reports\MetricasCache;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * "Por que perdemos": agrega as perdas de um período por motivo.
 *
 * Lê `perdas`, nunca o estado atual das entidades. É a diferença entre
 * responder "por que perdemos em agosto" e "por que os atualmente perdidos
 * foram perdidos" — um lead reaberto em setembro continua sendo uma perda de
 * agosto, e some da segunda resposta.
 *
 * Recebe um `Periodo` e fica em cache pela mesma chave versionada das
 * métricas (MetricasCache): registrar uma perda passa pelo lead/projeto e
 * invalida a versão.
 */
class RelatorioDePerdas
{
    /**
     * 90 dias, não 30: perda é evento raro comparado a criação de lead, e uma
     * janela de 30 dias volta vazia com frequência suficiente para o usuário
     * concluir que o relatório está quebrado.
     */
    public const DIAS_PADRAO = 90;

    /**
     * @param  'lead'|'projeto'|null  $tipo  null = os dois
     * @return array<string, mixed>
     */
    public function resumo(?Periodo $periodo = null, ?string $tipo = null): array
    {
        $periodo ??= self::periodoPadrao();

        $chave = MetricasCache::chave(app(CurrentTenant::class)->id(), null, null, 'perdas:'.($tipo ?? 'todos').':'.$periodo->hash());

        return Cache::remember($chave, (int) config('reports.metricas_ttl', 300), fn () => $this->calcular($periodo, $tipo));
    }

    /** 90 dias, não 30 (ver DIAS_PADRAO). */
    public static function periodoPadrao(): Periodo
    {
        return Periodo::customizado(Carbon::now()->subDays(self::DIAS_PADRAO), Carbon::now());
    }

    private function calcular(Periodo $periodo, ?string $tipo): array
    {
        $de = $periodo->inicio();
        $ate = $periodo->fim();

        $base = Perda::query()
            ->noPeriodo($de, $ate)
            ->when($tipo === 'lead', fn ($q) => $q->deLeads())
            ->when($tipo === 'projeto', fn ($q) => $q->deProjetos());

        // Uma consulta agregada em vez de carregar as linhas: o relatório soma,
        // não lista. `withTrashed` no motivo para que um motivo arquivado ainda
        // apareça com o nome certo nas perdas que já o citam.
        $porMotivo = (clone $base)
            ->selectRaw('motivo_perda_id, COUNT(*) as total, COALESCE(SUM(valor), 0) as valor')
            ->groupBy('motivo_perda_id')
            ->with(['motivo' => fn ($q) => $q->withTrashed()])
            ->orderByDesc('total')
            ->get();

        $total = (int) $porMotivo->sum('total');

        return [
            'periodo' => [
                'de' => $de->toDateString(),
                'ate' => $ate->toDateString(),
                'preset' => $periodo->chave(),
                'rotulo' => $periodo->rotulo(),
            ],
            'tipo' => $tipo,
            'total' => $total,
            'valor_total' => (float) $porMotivo->sum('valor'),
            'por_motivo' => $porMotivo->map(fn (Perda $linha) => [
                'motivo_perda_id' => $linha->motivo_perda_id,
                'descricao' => $linha->motivo?->descricao ?? 'Motivo removido',
                'arquivado' => (bool) $linha->motivo?->trashed(),
                'total' => (int) $linha->total,
                'valor' => (float) $linha->valor,
                // Percentual sobre a contagem, não sobre o valor: "40% das
                // perdas foram por preço" é a leitura que o nome do relatório
                // promete. O valor vai ao lado, para quem quiser a outra.
                'percentual' => $total > 0 ? round($linha->total / $total * 100, 1) : 0.0,
            ])->values(),
            'por_tipo' => [
                'lead' => (int) (clone $base)->deLeads()->count(),
                'projeto' => (int) (clone $base)->deProjetos()->count(),
            ],
        ];
    }
}
