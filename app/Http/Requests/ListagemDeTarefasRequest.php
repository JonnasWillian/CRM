<?php

namespace App\Http\Requests;

use App\Http\Requests\Listagem\ListagemRequest;
use Illuminate\Validation\Rule;

/** Lista global de tarefas (página Tarefas). */
class ListagemDeTarefasRequest extends ListagemRequest
{
    public const ESCOPOS = ['atrasadas', 'hoje', 'proximos_7', 'pendentes', 'concluidas', 'todas'];

    protected function colunasOrdenaveis(): array
    {
        return [
            'data_limite' => 'tarefas.data_limite',
            'created_at' => 'tarefas.created_at',
            'titulo' => 'tarefas.titulo',
        ];
    }

    protected function ordenacaoPadrao(): array
    {
        return ['data_limite', 'asc'];
    }

    protected function filtros(): array
    {
        return [
            'escopo' => ['nullable', Rule::in(self::ESCOPOS)],
            'user_id' => ['nullable', 'integer'],
        ];
    }
}
