<?php

namespace App\Http\Requests;

use App\Models\Estagio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Criação e edição de estágio.
 *
 * `tipo` é obrigatório e fechado na lista de Estagio::TIPOS. Não há default
 * silencioso: quem cria um estágio precisa dizer se ele é de pipeline aberto,
 * de ganho ou de perda, porque é essa resposta que as métricas vão ler. Um
 * default escolheria por quem não respondeu, e o erro só apareceria semanas
 * depois, num número de conversão errado.
 */
class EstagioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configuracoes.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'descricao' => ['required', 'string', 'min:2', 'max:255'],
            'tipo' => ['required', Rule::in(Estagio::TIPOS)],
            // Hex de 7 caracteres (#rrggbb). A UI oferece uma paleta, mas a
            // validação aceita qualquer cor válida — o campo é do tenant.
            'cor' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'ordem' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'descricao.required' => 'O nome do estágio é obrigatório.',
            'descricao.min' => 'O nome do estágio deve ter no mínimo :min caracteres.',
            'tipo.required' => 'Escolha o tipo do estágio: aberto, ganho ou perdido.',
            'tipo.in' => 'Tipo inválido. Use aberto, ganho ou perdido.',
            'cor.regex' => 'Informe a cor no formato #rrggbb.',
        ];
    }
}
