<?php

namespace App\Support\Db;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Grava a nova ordem de N linhas num único UPDATE:
 *
 *   UPDATE t SET ordem = CASE id WHEN 7 THEN 1 WHEN 3 THEN 2 … END WHERE id IN (7, 3, …)
 *
 * Antes era um UPDATE por id dentro de uma transação — N viagens ao banco
 * para arrastar um estágio de lugar. Os ids e posições são inteiros
 * (validados pelo request) e entram no SQL já convertidos, sem binding.
 *
 * O builder recebido já carrega os escopos que importam (TenantScope,
 * `where('funil_id')`, `withTrashed()`): uma linha fora deles não casa no
 * WHERE e não é tocada.
 */
final class ReordenarPorIds
{
    /**
     * @param  list<int|string>  $idsNaOrdem
     * @return int linhas afetadas
     */
    public static function aplicar(Builder $query, array $idsNaOrdem, string $coluna = 'ordem'): int
    {
        $ids = array_values(array_map('intval', $idsNaOrdem));

        if ($ids === []) {
            return 0;
        }

        $chave = $query->getModel()->getQualifiedKeyName();
        $casos = '';

        foreach ($ids as $posicao => $id) {
            $casos .= ' WHEN '.$id.' THEN '.($posicao + 1);
        }

        return $query
            ->whereIn($chave, $ids)
            ->update([$coluna => DB::raw('CASE '.$chave.$casos.' END')]);
    }
}
