<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Restaurar em massa. Permissão de classe aqui; a de instância é o escopo (o que o agente enxerga). */
class RestaurarDaLixeiraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leads.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(['leads', 'projetos'])],
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['integer'],
        ];
    }
}
