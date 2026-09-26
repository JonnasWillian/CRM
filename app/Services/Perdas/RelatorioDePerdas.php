<?php

namespace App\Services\Perdas;

use App\Models\Perda;
use App\Models\Projeto;
use App\Models\Usuario;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * "Por que perdemos": agrega as perdas de um período por motivo.
 *
 * Lê `perdas`, nunca o estado atual das entidades. É a diferença entre
 * responder "por que perdemos em agosto" e "por que os atualmente perdidos
 * foram perdidos" — um lead reaberto em setembro continua sendo uma perda de
 * agosto, e some da segunda resposta.
 *
 * O período ainda não usa o objeto `Periodo` do spec da camada de relatórios,
 * que não foi implementado. Quando aquela camada entrar, este serviço passa a
 * receber `Periodo` no lugar de duas datas, e ganha cache pela chave composta.
 * Até lá são duas datas e nenhuma cache — o volume de perdas é baixo e a
 * consulta é indexada por (tenant_id, created_at).
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
    public function resumo(?CarbonInterface $de = null, ?CarbonInterface $ate = null, ?string $tipo = null): array
    {
        $de = $de ? $de->copy()->startOfDay() : Carbon::now()->subDays(self::DIAS_PADRAO)->startOfDay();
        $ate = $ate ? $ate->copy()->endOfDay() : Carbon::now()->endOfDay();

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
