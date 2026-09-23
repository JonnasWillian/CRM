<?php

namespace App\Http\Requests;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação e edição de funil.
 *
 * `authorize()` repete o gate que a rota já aplica via middleware
 * `can:configuracoes.manage`. A redundância é proposital: é uma fronteira de
 * autorização, e uma rota adicionada depois sem o middleware não deve abrir o
 * cadastro de funis por esquecimento.
 */
class FunilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configuracoes.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'nome' => [
                'required',
                'string',
                'min:2',
                'max:255',
                // Dois funis com o mesmo nome no mesmo tenant tornam o seletor
                // do Kanban ambíguo. O filtro por tenant é explícito porque
                // regras `unique` consultam o banco direto e não passam pelo
                // TenantScope.
                Rule::unique('funis', 'nome')
                    ->where('tenant_id', app(CurrentTenant::class)->id())
                    ->whereNull('deleted_at')
                    ->ignore($this->route('funil')?->id),
            ],
            'descricao' => ['nullable', 'string', 'max:255'],
            'ordem' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O nome do funil é obrigatório.',
            'nome.min' => 'O nome do funil deve ter no mínimo :min caracteres.',
            'nome.unique' => 'Já existe um funil com este nome.',
        ];
    }
}
