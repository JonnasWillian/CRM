<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Respostas do assistente de ajuda. Nome, e-mail da conta e empresa NÃO são
 * aceitos daqui: o controller usa os do usuário autenticado. Do navegador
 * só vêm as escolhas (listas fechadas), o texto e um e-mail de contato.
 */
class ChamadoDeSuporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->tenant_id !== null;
    }

    protected function prepareForValidation(): void
    {
        $mensagem = $this->input('mensagem');
        if (is_string($mensagem)) {
            // Sem caracteres de controle (ficam só quebra de linha e tab).
            $limpa = preg_replace('/[^\P{C}\n\t]/u', '', str_replace(["\r\n", "\r"], "\n", $mensagem)) ?? '';
            $this->merge(['mensagem' => trim($limpa)]);
        }
        if (is_string($this->input('email_contato'))) {
            $this->merge(['email_contato' => mb_strtolower(trim($this->input('email_contato'))) ?: null]);
        }
    }

    public function rules(): array
    {
        $em = fn (string $grupo) => Rule::in(array_keys(config("suporte.{$grupo}")));

        return [
            'tipo' => ['required', 'string', $em('tipos')],
            'area' => ['required', 'string', $em('areas')],
            'impacto' => ['nullable', 'required_if:tipo,problema', 'string', $em('impactos')],
            'mensagem' => ['required', 'string', 'min:10', 'max:3000'],
            'nota' => ['nullable', 'integer', 'between:0,10'],
            'email_contato' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'acionar_suporte' => ['boolean'],
            // Só o caminho da página dentro do app, para o suporte reproduzir.
            'pagina' => ['nullable', 'string', 'max:255', 'regex:/^\/[^\s<>"\']*$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.*' => 'Escolha um dos assuntos.',
            'area.*' => 'Escolha uma das áreas.',
            'impacto.*' => 'Diga o quanto isso atrapalha.',
            'mensagem.required' => 'Conte o que aconteceu.',
            'mensagem.min' => 'Conte um pouco mais: pelo menos 10 caracteres.',
            'mensagem.max' => 'O texto pode ter até 3.000 caracteres.',
            'nota.*' => 'A nota vai de 0 a 10.',
            'email_contato.*' => 'Informe um e-mail válido para contato.',
            'pagina.*' => 'Página inválida.',
        ];
    }
}
