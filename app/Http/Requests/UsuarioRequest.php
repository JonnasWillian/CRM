<?php

namespace App\Http\Requests;

use App\Support\Leads\RegrasDeLead;
use App\Support\Perdas\RegrasDePerda;
use App\Support\Telefone;
use Illuminate\Foundation\Http\FormRequest;

class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permissão de CLASSE primeiro: sem `leads.manage` não há o que checar
        // na instância — quem não mexe em lead nenhum não passa daqui, ponto.
        if (! ($this->user()?->can('leads.manage') ?? false)) {
            return false;
        }

        // Na edição a autorização da INSTÂNCIA precisa acontecer AQUI, e não no
        // corpo do controller: o FormRequest é validado antes de o método rodar,
        // e um 422 contra um lead alheio já revelaria que o id existe no tenant.
        // No cadastro (POST) não há lead na rota — nada a checar aqui além disso.
        $usuario = $this->route('usuario');

        return $usuario === null || $this->user()->can('update', $usuario);
    }

    /**
     * 404 existe para esconder existência. Onde não há existência a esconder,
     * a resposta honesta é 403.
     *
     * Três situações, uma regra:
     *
     *   sem modelo na rota (POST)        -> 403. Não há recurso.
     *   modelo que o autor JÁ PODE VER   -> 403. Ele sabe que existe; mentir
     *                                       "não encontrado" produz o bug de
     *                                       "o lead sumiu ao salvar".
     *   modelo que ele não pode ver      -> 404. Aqui 403 confirmaria o id e
     *                                       deixaria enumerar a carteira dos
     *                                       colegas, um id por vez.
     *
     * O caso do meio é o que faltava, e é o que o usuário sem papel vivia: ele
     * abre o próprio lead (a policy de view deixa, ele é o dono), edita, salva
     * e recebe 404. O certo é 403 — a negativa é de CLASSE, ele não edita lead
     * NENHUM, e o recurso é dele.
     *
     * Não vaza nada: quem cai no 403 aqui já passaria no `view` do mesmo
     * recurso. E quem não tem `leads.manage` recebe 403 para todo lead que
     * enxerga e 404 para todo lead que não enxerga — que é exatamente a
     * fronteira que ele já conhecia.
     */
    protected function failedAuthorization(): void
    {
        $usuario = $this->route('usuario');

        if ($usuario === null || ($this->user()?->can('view', $usuario) ?? false)) {
            parent::failedAuthorization();

            return;
        }

        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
    }

    /**
     * O telefone chega em qualquer forma — com máscara (Dashboard, Perfil),
     * sem máscara (quick-add do Kanban) — e é normalizado aqui, antes das
     * regras. Nenhuma tela precisa mais tirar a máscara.
     *
     * Só texto é normalizado: um array segue como veio e a regra `string`
     * recusa, sem erro 500.
     */
    protected function prepareForValidation(): void
    {
        $telefone = $this->input('telefone');

        if ($telefone === null || is_string($telefone) || is_int($telefone)) {
            $this->merge(['telefone' => Telefone::normalizar($telefone === null ? null : (string) $telefone)]);
        }
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
            ...RegrasDeLead::campos(null, $this->filled('funil_id') ? $this->integer('funil_id') : null),
            // Responsável escolhido no cadastro: só vale para quem tem
            // leads.view-all (CadastrarLead ignora para os demais). Trocar o
            // responsável de um lead existente é a transferência, não a edição.
            'user_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('users', 'id')->where('tenant_id', app(\App\Support\Tenancy\CurrentTenant::class)->id())],
            ...RegrasDePerda::campos(),
        ];
    }

    private function updateRules()
    {
        return [
            // O model vem do route model binding, não do corpo: é o mesmo lead
            // que o controller autoriza via policy, e não depende do cliente
            // reenviar 'id' no payload.
            ...RegrasDeLead::campos($this->route('usuario')?->id, $this->filled('funil_id') ? $this->integer('funil_id') : null),
            ...RegrasDePerda::campos(),
        ];
    }

    public function messages(): array
    {
        return [
            ...RegrasDePerda::mensagens(),
            ...RegrasDeLead::mensagens(),
        ];
    }

    public function attributes(): array
    {
        return [
            'nome' => 'nome',
            'email' => 'email',
            'telefone' => 'telefone',
            'descricao' => 'descrição',
            'user_id' => 'responsável',
        ];
    }
}
