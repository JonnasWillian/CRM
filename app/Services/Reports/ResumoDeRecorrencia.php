<?php

namespace App\Services\Reports;

use App\Models\Projeto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Contratos recorrentes divididos em "faturando" e "em projeção".
 *
 * Fatura o contrato vigente (já começou, não terminou) e não cancelado,
 * qualquer que seja o status: o status do projeto descreve a entrega (Em
 * desenvolvimento, Pausado, Concluído), não a venda. Cancelado = status de
 * perda; os meses até o cancelamento continuam faturados no período.
 * Em projeção: o que falta dos vigentes e o total dos que ainda vão começar.
 */
class ResumoDeRecorrencia
{
    /** @param  Builder  $idsDeLeads  subconsulta de usuarios.id */
    public static function contratos(Builder $idsDeLeads): Collection
    {
        return Projeto::query()
            ->where('tipo', 'recorrente')
            ->whereIn('usuario_id', $idsDeLeads)
            ->with(['aditivos', 'usuario:id,user_id'])
            ->get();
    }

    /** @return array{mrr:float, contratos_vigentes:int, contratos_a_iniciar:int, no_periodo:float, acumulado:float, a_realizar:float, vencendo_30d:int} */
    public static function resumir(iterable $contratos, Periodo $periodo): array
    {
        $r = ['mrr' => 0.0, 'contratos_vigentes' => 0, 'contratos_a_iniciar' => 0, 'no_periodo' => 0.0, 'acumulado' => 0.0, 'a_realizar' => 0.0, 'vencendo_30d' => 0];

        foreach ($contratos as $projeto) {
            $c = $projeto->cronograma();
            if ($c === null) {
                continue;
            }
            $r['no_periodo'] += $c->valorEntre($periodo->inicio(), $periodo->fim());

            match ($c->situacao()) {
                'vigente' => [$r['mrr'] += $c->valorMesAtual(), $r['contratos_vigentes']++, $r['a_realizar'] += $c->valorARealizar(), $r['acumulado'] += $c->valorRealizado()],
                'a_iniciar' => [$r['contratos_a_iniciar']++, $r['a_realizar'] += $c->valorTotal()],
                'encerrado' => $r['acumulado'] += $c->valorRealizado(),
                default => null, // cancelado: só o que faturou no período
            };
            $r['vencendo_30d'] += $c->venceEmBreve() ? 1 : 0;
        }

        foreach (['mrr', 'no_periodo', 'acumulado', 'a_realizar'] as $k) {
            $r[$k] = round($r[$k], 2);
        }

        return $r;
    }
}
