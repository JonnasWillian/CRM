<?php

namespace App\Support\Listagem;

use Illuminate\Database\Eloquent\Builder;

/**
 * Ordenação já validada: o alias que o cliente pediu (`sort`), a direção e a
 * coluna SQL que ele representa (vinda da lista fechada do request).
 */
final readonly class Ordenacao
{
    public function __construct(
        public string $por,
        public string $dir,
        public string $coluna,
    ) {}

    /** Aplica a ordem e um desempate estável, para a paginação não repetir nem pular linhas. */
    public function aplicar(Builder $query, string $desempate = 'id'): Builder
    {
        return $query->orderBy($this->coluna, $this->dir)->orderBy($desempate, $this->dir);
    }

    public function toArray(): array
    {
        return ['por' => $this->por, 'dir' => $this->dir];
    }
}
