<?php

namespace App\Http\Requests;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return $this->storeRules();
        }

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return $this->updateRules();
        }

        return [];
    }

    private function storeRules()
    {
        return [
            'nome' => 'required|string|min:5',
            'email' => ['required', 'email', $this->emailUnicoNoTenant()],
            'telefone' => 'required|string',
            'descricao' => 'nullable|string',
            'tag_id' => 'nullable',
        ];
    }

    private function updateRules()
    {
        return [
            'nome' => 'required|string|min:5',
            'email' => [
                'required',
                'email',
                // O id vem da rota, não do corpo: é o mesmo identificador que o
                // controller usa no findOrFail(), e não depende do cliente
                // reenviar 'id' no payload.
                $this->emailUnicoNoTenant()->ignore($this->route('id')),
            ],
            'telefone' => 'required|string|min:7',
            'descricao' => 'nullable|string',
            'tag_id' => 'nullable',
        ];
    }

    /**
     * Regras `unique:` consultam o banco diretamente e não passam pelo
     * Eloquent, portanto o TenantScope não se aplica a elas. O filtro por
     * tenant precisa ser explícito, ou um lead de outro tenant bloquearia
     * o cadastro aqui (e a mensagem de erro denunciaria sua existência).
     */
    private function emailUnicoNoTenant(): Unique
    {
        return Rule::unique('usuarios', 'email')
            ->where('tenant_id', app(CurrentTenant::class)->id());
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O campo nome é obrigatório.',
            'nome.min' => 'O nome deve ter no mínimo :min caracteres.',
            'nome.string' => 'O nome deve ser um texto válido.',

            'email.required' => 'O email é obrigatório.',
            'email.email' => 'Informe um email válido.',
            'email.unique' => 'Este email já está sendo utilizado.',

            'telefone.required' => 'O telefone é obrigatório.',
            'telefone.min' => 'O telefone deve ter pelo menos :min caracteres.',
            'telefone.string' => 'O telefone deve ser um texto válido.',

            'descricao.string' => 'A descrição deve ser um texto válido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nome' => 'nome',
            'email' => 'email',
            'telefone' => 'telefone',
            'descricao' => 'descrição',
            'user_id' => 'usuário',
        ];
    }
}