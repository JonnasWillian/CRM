<?php

namespace App\Support\Listagem;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Formato único de toda listagem paginada — JSON da API ou props do Inertia:
 *
 *   { data: [...], meta: { current_page, last_page, per_page, total, from, to }, sort: { por, dir }, filtros: {...} }
 */
final class Paginacao
{
    public const PADRAO = 25;

    public static function maximo(): int
    {
        return (int) config('leads.listagem.max_por_pagina', 100);
    }

    /** Acima do teto é limitado, não recusado; abaixo de 1 vira 1. */
    public static function clamp(int|string|null $porPagina, ?int $padrao = null): int
    {
        $padrao ??= (int) config('leads.listagem.por_pagina', self::PADRAO);

        return min(max((int) ($porPagina ?: $padrao), 1), self::maximo());
    }

    public static function resposta(LengthAwarePaginator $pagina, ?Ordenacao $ordenacao = null, array $filtros = []): array
    {
        return [
            'data' => $pagina->items(),
            'meta' => [
                'current_page' => $pagina->currentPage(),
                'last_page' => $pagina->lastPage(),
                'per_page' => $pagina->perPage(),
                'total' => $pagina->total(),
                'from' => $pagina->firstItem(),
                'to' => $pagina->lastItem(),
            ],
            'sort' => $ordenacao?->toArray(),
            'filtros' => $filtros,
        ];
    }
}
