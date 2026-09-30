<?php

namespace App\Http\Requests;

use App\Support\Arquivos\PoliticaDeUpload;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Anexo de LEAD.
 *
 * Servia também o anexo de PROJETO até esta separação — o mesmo campo
 * `usuario_id` carregava ora um id de lead, ora um id de projeto, e só
 * funcionava porque a regra era `required` e nada mais. `ProjetoAnexoRequest`
 * assumiu o segundo caso.
 */
class ArquivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('leads.manage')) {
            return false;
        }

        // O lead vem de um campo do CORPO, não da rota: o route model binding
        // nunca o enxerga, então a policy de instância não roda sozinha aqui.
        // Sem esta checagem dá para anexar arquivo ao lead de um colega que
        // você nem consegue abrir.
        //
        // Todas as negativas convergem para a mesma resposta — sem permissão
        // de classe, id inexistente e id de terceiro são indistinguíveis — então
        // não há oráculo de existência a fechar com 404 aqui.
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

    public function rules(): array
    {
        return [
            'arquivo' => PoliticaDeUpload::regras(),
            'nome' => ['nullable', 'string', 'max:255'],
            'usuario_id' => [
                'required',
                'integer',
                Rule::exists('usuarios', 'id')->where('tenant_id', app(CurrentTenant::class)->id()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            ...PoliticaDeUpload::mensagens(),
            'nome.max' => 'O nome do arquivo pode ter no máximo 255 caracteres.',
            'usuario_id.required' => 'O usuário detentor do arquivo é obrigatório',
            'usuario_id.exists' => 'Usuário inválido.',
        ];
    }
}
