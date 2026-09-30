<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Só valida a FORMA dos filtros. Quem decide o que cada agente enxerga é
 * Usuario::scopeVisibleTo, dentro de ListagemDeLeads — por isso authorize()
 * é true: não há recurso de terceiro identificado na requisição.
 */
class ListagemDeLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'busca' => ['nullable', 'string', 'max:100'],
            'estagios' => ['nullable', 'array'],
            'estagios.*' => ['integer'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
            'status' => ['nullable', 'in:todos,arquivado,aberto,sem_projeto'],
            'page' => ['nullable', 'integer', 'min:1'],
            // Sem `max`: acima do teto é limitado, não recusado.
            'per_page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return ['ate.after_or_equal' => 'A data final precisa ser igual ou posterior à inicial.'];
    }
}
