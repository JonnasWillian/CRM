<?php

namespace App\Http\Requests;

use App\Support\LimitesDeTexto;
use App\Support\Perdas\RegrasDePerda;
use Illuminate\Foundation\Http\FormRequest;

class ProjetoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permissão de CLASSE primeiro: sem `projetos.manage` não há o que
        // checar na instância — quem não mexe em projeto nenhum não passa
        // daqui, ponto.
        if (! $this->user()?->can('projetos.manage')) {
            return false;
        }

        // Edição: o projeto vem da rota e o binding o resolve. A autorização da
        // INSTÂNCIA precisa acontecer AQUI, e não no corpo do controller: o
        // FormRequest é validado antes de o método rodar, e um 422 contra um
        // projeto alheio já revelaria que o id existe no tenant.
        $projeto = $this->route('projeto');

        if ($projeto !== null) {
            return $this->user()->can('update', $projeto);
        }

        // Criação: o lead dono vem de um campo do CORPO, que o binding nunca
        // enxerga. Sem resolver e autorizar aqui, dá para criar um projeto
        // preso ao lead de um colega — ou a um id que nem existe.
        //
        // integer() e não input(): com `usuario_id` enviado como array,
        // find() viraria findMany() e devolveria uma Collection, que nunca é
        // null — a guarda abaixo passaria sozinha. integer() evita ISSO, mas
        // não da forma que parece: `(int) [qualquer array não vazio]` é
        // sempre 1 em PHP, não importa o conteúdo. Ou seja, authorize() acaba
        // decidindo sobre "o recurso de id 1", não sobre o que foi enviado —
        // hoje isso nega por acaso (id 1 raramente é o alvo pretendido), mas
        // se um dia for, autorizaria o recurso ERRADO.
        //
        // authorize() roda ANTES de rules(), então a regra `integer` do
        // campo em rules() não impede ESTA decisão específica. O que ela
        // garante é a rede de segurança: mesmo que authorize() acerte por
        // acidente, a validação recusa o array logo depois e nada malformado
        // chega ao controller ou ao banco.
        $lead = \App\Models\Usuario::find($this->integer('usuario_id'));

        return $lead !== null && $this->user()->can('update', $lead);
    }

    /**
     * Mesma regra de UsuarioRequest::failedAuthorization(), pelos mesmos
     * motivos: 404 só onde há existência a esconder.
     *
     *   sem projeto na rota (POST)       -> 403, mas isto NÃO é "sem
     *                                       recurso": em POST /api/projeto há
     *                                       um lead referenciado em
     *                                       `usuario_id`, e a negativa pode
     *                                       muito bem ser de INSTÂNCIA dele
     *                                       (lead alheio). É 403 mesmo assim
     *                                       porque `authorize()` já colapsa
     *                                       "lead inexistente" e "lead
     *                                       alheio" na mesma falha antes de
     *                                       chegar aqui — as duas produzem o
     *                                       mesmo `authorize() === false`, e
     *                                       o `$projeto` que esta função
     *                                       inspeciona (o da ROTA) é sempre
     *                                       null nesse caminho, então cai
     *                                       neste primeiro ramo.
     *   projeto que o autor JÁ PODE VER  -> 403. Negativa de classe sobre
     *                                       coisa que é dele; 404 aqui seria
     *                                       "o projeto sumiu ao salvar".
     *   projeto que ele não pode ver     -> 404. 403 confirmaria o id.
     */
    protected function failedAuthorization(): void
    {
        $projeto = $this->route('projeto');

        if ($projeto === null || ($this->user()?->can('view', $projeto) ?? false)) {
            parent::failedAuthorization();

            return;
        }

        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
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
            'descricao' => ['nullable', 'string', 'max:'.LimitesDeTexto::DESCRICAO],
            'preco' => 'nullable|numeric',
            'data_inicial' => 'nullable|date',
            'data_final' => 'nullable|date',
            'parcelas' => 'nullable|boolean',
            'qtd_parcelas' => 'nullable|numeric',
            'status_id' => ['required', $this->statusDoTenant()],
            'usuario_id' => 'required|integer',
            ...RegrasDePerda::campos(),
        ];
    }

    private function updateRules()
    {
        return [
            'nome' => ['required', 'string', 'min:5', 'max:'.LimitesDeTexto::NOME],
            'descricao' => ['nullable', 'string', 'max:'.LimitesDeTexto::DESCRICAO],
            'preco' => 'nullable|numeric',
            'data_inicial' => 'nullable|date',
            'data_final' => 'nullable|date',
            'parcelas' => 'nullable|boolean',
            'qtd_parcelas' => 'nullable|numeric',
            'status_id' => ['required', $this->statusDoTenant()],
            ...RegrasDePerda::campos(),
        ];
    }

    /**
     * `status_id` era só `required`. Três consequências, todas medidas:
     *
     *   status de OUTRO tenant -> 201, e o projeto fica gravado apontando para
     *                             uma linha de outra empresa;
     *   id inexistente         -> 500 (violação de FK), não 422;
     *   e a pior: `Projeto::estadoSeriaPerda()` consulta `Statu` pelo Eloquent,
     *             o TenantScope não resolve o status de fora, o método devolve
     *             `false` e a exigência de motivo de perda é CONTORNADA. Dava
     *             para criar um projeto já perdido, sem motivo, mandando o id
     *             de um status `is_lost` de qualquer outra empresa.
     *
     * A tabela é `status` (singular no plural do model `Statu`), conferido em
     * 2026_04_23_011055_status.php.
     *
     * Sem `whereNull('deleted_at')`, ao contrário das outras regras deste
     * projeto, e de propósito: `Projeto::status()` usa `withTrashed()` porque
     * um status arquivado continua nomeando os projetos antigos. Filtrar por
     * deleted_at aqui impediria de salvar um projeto que já aponta para um
     * status arquivado.
     */
    private function statusDoTenant(): \Illuminate\Validation\Rules\Exists
    {
        return \Illuminate\Validation\Rule::exists('status', 'id')
            ->where('tenant_id', app(\App\Support\Tenancy\CurrentTenant::class)->id());
    }

    public function messages(): array
    {
        return [
            ...RegrasDePerda::mensagens(),

            'nome.required' => 'O campo nome é obrigatório.',
            'nome.min' => 'O nome deve ter no mínimo :min caracteres.',
            'nome.string' => 'O nome deve ser um texto válido.',
            'nome.max' => 'O nome pode ter no máximo :max caracteres.',

            'descricao.string' => 'A descrição deve ser um texto válido.',
            'descricao.max' => 'A descrição pode ter no máximo :max caracteres.',

            'usuario_id.required' => 'O usuário responsável é obrigatório.',
            'status_id.required' => 'O Status é obrigatório.',
            'status_id.exists' => 'Status inválido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nome' => 'nome',
            'descricao' => 'descrição',
            'usuario_id' => 'usuário',
            'status_id' => 'status',
        ];
    }
}