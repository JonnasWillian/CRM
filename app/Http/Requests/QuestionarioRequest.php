<?php

namespace App\Http\Requests;

use App\Rules\TelefoneE164;
use App\Support\Telefone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Respostas do questionário de primeiro acesso; as opções vêm de config/onboarding.php. */
class QuestionarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('configuracoes.manage') ?? false;
    }

    /** Como no cadastro de lead: a tela manda o número mascarado, o servidor guarda E.164. */
    protected function prepareForValidation(): void
    {
        $telefone = $this->input('telefone');
        if ($telefone === null || is_string($telefone) || is_int($telefone)) {
            $this->merge(['telefone' => Telefone::normalizar($telefone === null ? null : (string) $telefone)]);
        }
    }

    public function rules(): array
    {
        $chaves = fn (string $grupo) => Rule::in(array_map('strval', array_keys(config("onboarding.{$grupo}"))));

        return [
            'experiencia' => ['required', $chaves('experiencias')],
            'cargo' => ['required', $chaves('cargos')],
            'ramo' => ['required', $chaves('ramos')],
            'tamanho' => ['required', $chaves('tamanhos')],
            'para_quem' => ['required', $chaves('para_quem')],
            'canais' => ['required', 'array', 'min:1'],
            'canais.*' => ['string', $chaves('canais')],
            'origens' => ['required', 'array', 'min:1'],
            'origens.*' => ['string', $chaves('origens')],
            'conheceu' => ['nullable', $chaves('conheceu')],
            'telefone' => ['nullable', 'string', new TelefoneE164],
            'dicas_whatsapp' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'experiencia.required' => 'Conte sua experiência com CRM.',
            'cargo.required' => 'Informe o seu cargo.',
            'ramo.required' => 'Informe o ramo de atividade da sua empresa.',
            'tamanho.required' => 'Informe quantas pessoas vão usar o sistema.',
            'para_quem.required' => 'Informe para quem você vende.',
            'canais.required' => 'Escolha ao menos um canal.',
            'canais.min' => 'Escolha ao menos um canal.',
            'origens.required' => 'Escolha ao menos uma origem.',
            'origens.min' => 'Escolha ao menos uma origem.',
            '*.in' => 'Escolha uma das opções.',
            'canais.*.in' => 'Escolha uma das opções.',
            'origens.*.in' => 'Escolha uma das opções.',
        ];
    }
}
