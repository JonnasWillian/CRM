<?php

namespace App\Http\Requests\Tarefas;

use App\Models\Tarefa;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;

/** `ids[]` de tarefas para concluir/excluir em massa; só as dos leads que o agente enxerga. */
class SelecaoDeTarefasRequest extends FormRequest
{
    public const TETO_DE_IDS = 200;

    /** Permissão de classe aqui; a de instância é o escopo da seleção (leads visíveis). */
    public function authorize(): bool
    {
        return $this->user()?->can('tarefas.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::TETO_DE_IDS],
            'ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return ['ids.required' => 'Selecione ao menos uma tarefa.', 'ids.max' => 'Selecione no máximo :max tarefas por vez.'];
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_values(array_unique(array_map('intval', $this->input('ids', []))));
    }

    public function selecao(): Builder
    {
        return Tarefa::whereIn('tarefas.id', $this->ids())
            ->whereIn('tarefas.usuario_id', Usuario::idsVisiveisPara($this->user()))
            ->orderBy('tarefas.id');
    }
}
