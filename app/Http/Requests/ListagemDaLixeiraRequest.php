<?php

namespace App\Http\Requests;

use App\Http\Requests\Listagem\ListagemRequest;
use Illuminate\Validation\Rule;

/** Lixeira: leads ou projetos excluídos, por data de exclusão ou nome. */
class ListagemDaLixeiraRequest extends ListagemRequest
{
    protected function colunasOrdenaveis(): array
    {
        return ['deleted_at' => 'deleted_at', 'nome' => 'nome'];
    }

    protected function ordenacaoPadrao(): array
    {
        return ['deleted_at', 'desc'];
    }

    protected function filtros(): array
    {
        return [
            'tipo' => ['nullable', Rule::in(['leads', 'projetos'])],
            'user_id' => ['nullable', 'integer'],
        ];
    }
}
