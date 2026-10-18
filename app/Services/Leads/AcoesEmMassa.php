<?php

namespace App\Services\Leads;

use App\Models\Estagio;
use App\Models\Usuario;
use App\Services\Perdas\AplicarTransicao;
use App\Support\Reports\MetricasCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ações em massa sobre uma seleção de leads (Builder já restrito ao que o
 * agente enxerga). Lead a lead, em lotes por id: cada um passa pelo mesmo
 * caminho da ação individual (AplicarTransicao, observers, activity log),
 * então nada que a tela individual registra fica sem registro aqui.
 *
 * @return array{afetados:int, ignorados:int}
 */
class AcoesEmMassa
{
    public const LOTE = 100;

    public function __construct(private readonly AplicarTransicao $transicao) {}

    /**
     * Só leads do mesmo funil do destino e que ainda não estão nele.
     *
     * @param  array{motivo_perda_id:int|null, observacao:string|null}  $perda
     */
    public function moverEstagio(Builder $selecao, int $pedidos, Estagio $destino, array $perda): array
    {
        return MetricasCache::emLote(fn () => $this->mover($selecao, $pedidos, $destino, $perda));
    }

    private function mover(Builder $selecao, int $pedidos, Estagio $destino, array $perda): array
    {
        $afetados = 0;

        $selecao
            ->where('usuarios.funil_id', $destino->funil_id)
            ->where(fn ($q) => $q->whereNull('usuarios.estagio_id')->orWhere('usuarios.estagio_id', '!=', $destino->id))
            ->chunkById(self::LOTE, function (Collection $leads) use (&$afetados, $destino, $perda) {
                foreach ($leads as $lead) {
                    ($this->transicao)($lead, ['estagio_id' => $destino->id], $perda);
                    $afetados++;
                }
            }, 'usuarios.id', 'id');

        return ['afetados' => $afetados, 'ignorados' => max(0, $pedidos - $afetados)];
    }

    public function lixeira(Builder $selecao, int $pedidos): array
    {
        return MetricasCache::emLote(fn () => $this->excluir($selecao, $pedidos));
    }

    private function excluir(Builder $selecao, int $pedidos): array
    {
        $afetados = 0;

        $selecao->chunkById(self::LOTE, function (Collection $leads) use (&$afetados) {
            foreach ($leads as $lead) {
                /** @var Usuario $lead */
                $lead->delete();
                $afetados++;
            }
        }, 'usuarios.id', 'id');

        return ['afetados' => $afetados, 'ignorados' => max(0, $pedidos - $afetados)];
    }
}
