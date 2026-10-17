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
        // checar na instância.
        if (! $this->user()?->can('projetos.manage')) {
            return false;
        }

        // Edição: o projeto vem da rota. Criação: o lead dono vem da rota
        // (`POST /api/leads/{usuario}/projetos`). Nos dois casos a instância
        // é autorizada AQUI, antes da validação — um 422 contra um recurso
        // alheio já revelaria que o id existe no tenant.
        $projeto = $this->route('projeto');

        if ($projeto !== null) {
            return $this->user()->can('update', $projeto);
        }

        $lead = $this->route('usuario');

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
        $alvo = $this->route('projeto') ?? $this->route('usuario');

        if ($alvo === null || ($this->user()?->can('view', $alvo) ?? false)) {
            parent::failedAuthorization();

            return;
        }

        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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

            'status_id.required' => 'O Status é obrigatório.',
            'status_id.exists' => 'Status inválido.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nome' => 'nome',
            'descricao' => 'descrição',
            'status_id' => 'status',
        ];
    }
}
