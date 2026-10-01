<?php

namespace App\Http\Requests;

use App\Support\Arquivos\PoliticaDeUpload;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Anexo de PROJETO.
 *
 * O campo se chama `usuario_id` e carrega um id de projeto — o controller faz
 * `'projeto_id' => $request->usuario_id` e o frontend envia
 * `fd.append('usuario_id', projetoId)`. O nome está errado e é conhecido;
 * renomear exige mexer no frontend e fica como tarefa própria. O que esta
 * classe corrige é a validação, que antes aceitava qualquer inteiro.
 */
class ProjetoAnexoRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('projetos.manage')) {
            return false;
        }

        // O projeto vem de um campo do CORPO, não da rota: o route model
        // binding nunca o enxerga, então a policy de instância não roda
        // sozinha aqui. Sem esta checagem dá para anexar arquivo ao projeto
        // de um colega que você nem consegue abrir.
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
        $projeto = \App\Models\Projeto::find($this->integer('usuario_id'));

        return $projeto !== null && $this->user()->can('update', $projeto);
    }

    public function rules(): array
    {
        return [
            'arquivo' => PoliticaDeUpload::regras(),
            'nome' => ['nullable', 'string', 'max:255'],
            'usuario_id' => [
                'required',
                'integer',
                Rule::exists('projetos', 'id')
                    ->where('tenant_id', app(CurrentTenant::class)->id())
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            ...PoliticaDeUpload::mensagens(),
            'usuario_id.required' => 'O projeto do anexo é obrigatório.',
            'usuario_id.exists' => 'Projeto inválido.',
        ];
    }
}
