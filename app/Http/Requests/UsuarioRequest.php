<?php

namespace App\Http\Requests;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
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
            'funil_id' => ['nullable', $this->funilDoTenant()],
            'estagio_id' => ['nullable', $this->estagioDoTenant()],
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
            'funil_id' => ['nullable', $this->funilDoTenant()],
            'estagio_id' => ['nullable', $this->estagioDoTenant()],
        ];
    }

    /**
     * `estagio_id` e `funil_id` só eram `nullable`, sem `exists`. Passava
     * qualquer inteiro — inclusive o de outro tenant. Com funis por tenant isso
     * deixa de ser teórico: o mesmo id significa estágios diferentes em
     * empresas diferentes.
     */
    private function funilDoTenant(): Exists
    {
        return Rule::exists('funis', 'id')
            ->where('tenant_id', app(CurrentTenant::class)->id())
            ->whereNull('deleted_at');
    }

    /**
     * Quando o payload traz os dois, o estágio tem de ser do funil informado.
     *
     * Sem este acoplamento, a edição de lead (PUT /usuarios/{id}) seria um
     * caminho aberto para o estado que MoverLeadDeFunil e patchEstagio recusam:
     * lead com `funil_id` de um funil e `estagio_id` de outro. O card sumiria
     * do quadro sem erro nenhum aparecer.
     */
    private function estagioDoTenant(): Exists
    {
        $regra = Rule::exists('estagios', 'id')
            ->where('tenant_id', app(CurrentTenant::class)->id());

        if ($this->filled('funil_id')) {
            $regra->where('funil_id', $this->integer('funil_id'));
        }

        return $regra;
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