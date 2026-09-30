<?php

namespace App\Http\Requests;

use App\Rules\EmailDeLeadDisponivel;
use App\Rules\TelefoneE164;
use App\Support\LimitesDeTexto;
use App\Support\Perdas\RegrasDePerda;
use App\Support\Telefone;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

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

        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
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
            'nome' => ['required', 'string', 'min:5', 'max:'.LimitesDeTexto::NOME],
            'email' => ['required', 'email', 'max:'.LimitesDeTexto::EMAIL, new EmailDeLeadDisponivel()],
            'telefone' => ['nullable', 'string', new TelefoneE164()],
            'descricao' => ['nullable', 'string', 'max:'.LimitesDeTexto::DESCRICAO],
            'funil_id' => ['nullable', $this->funilDoTenant()],
            'estagio_id' => ['nullable', $this->estagioDoTenant()],
            ...RegrasDePerda::campos(),
        ];
    }

    private function updateRules()
    {
        return [
            'nome' => ['required', 'string', 'min:5', 'max:'.LimitesDeTexto::NOME],
            'email' => [
                'required',
                'email',
                'max:'.LimitesDeTexto::EMAIL,
                // O model vem do route model binding, não do corpo: é o mesmo
                // lead que o controller autoriza via policy, e não depende do
                // cliente reenviar 'id' no payload.
                new EmailDeLeadDisponivel($this->route('usuario')?->id),
            ],
            'telefone' => ['nullable', 'string', new TelefoneE164()],
            'descricao' => ['nullable', 'string', 'max:'.LimitesDeTexto::DESCRICAO],
            'funil_id' => ['nullable', $this->funilDoTenant()],
            'estagio_id' => ['nullable', $this->estagioDoTenant()],
            ...RegrasDePerda::campos(),
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

    public function messages(): array
    {
        return [
            ...RegrasDePerda::mensagens(),
            'nome.required' => 'O campo nome é obrigatório.',
            'nome.min' => 'O nome deve ter no mínimo :min caracteres.',
            'nome.string' => 'O nome deve ser um texto válido.',
            'nome.max' => 'O nome pode ter no máximo :max caracteres.',

            'email.required' => 'O email é obrigatório.',
            'email.email' => 'Informe um email válido.',
            'email.max' => 'O email pode ter no máximo :max caracteres.',

            'telefone.string' => 'O telefone deve ser um texto válido.',

            'descricao.string' => 'A descrição deve ser um texto válido.',
            'descricao.max' => 'A descrição pode ter no máximo :max caracteres.',
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