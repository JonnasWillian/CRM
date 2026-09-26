<?php

namespace App\Http\Requests;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação e edição de motivo de perda.
 *
 * `authorize()` repete o gate que a rota aplica via `can:configuracoes.manage`,
 * pelo mesmo motivo que em FunilRequest: é fronteira de autorização, e uma rota
 * adicionada depois sem o middleware não deve abrir o catálogo por esquecimento.
 */
class MotivoPerdaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configuracoes.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'descricao' => [
                'required',
                'string',
                'min:2',
                'max:255',
                // Dois motivos com o mesmo nome tornam o relatório ambíguo:
                // duas linhas "Preço" que não somam. O filtro por tenant é
                // explícito porque `unique` consulta o banco direto e não passa
                // pelo TenantScope.
                Rule::unique('motivos_perda', 'descricao')
                    ->where('tenant_id', app(CurrentTenant::class)->id())
                    ->whereNull('deleted_at')
                    ->ignore($this->route('motivo')?->id),
            ],
            'ordem' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'descricao.required' => 'O nome do motivo é obrigatório.',
            'descricao.min' => 'O nome do motivo deve ter no mínimo :min caracteres.',
            'descricao.unique' => 'Já existe um motivo de perda com este nome.',
        ];
    }
}
