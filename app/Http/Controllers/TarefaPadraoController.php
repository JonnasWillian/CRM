<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TarefaPadrao;
use App\Models\Tarefa;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class TarefaPadraoController extends Controller
{
    public function index(Request $request)
    {
        $padroes = TarefaPadrao::where('user_id', auth()->id())
            ->orderBy('titulo')
            ->get();

        return response()->json($padroes);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'titulo'    => 'required|string|max:255',
                'anotacao'  => 'nullable|string',
                'prazo_dias' => 'required|integer|min:0',
            ]);

            $validated['user_id'] = auth()->id();

            $padrao = TarefaPadrao::create($validated);

            return response()->json(['message' => 'Modelo criado com sucesso', 'data' => $padrao], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json(['erros' => $error->errors()], 422);
        }
    }

    /*
     * O authorize() fica FORA do try, como nos demais controllers do branch.
     *
     * Dentro dele, a AuthorizationException que a policy levanta caía no
     * `catch (\Exception)` e voltava como `{"error":"Modelo não encontrado"}` —
     * uma quarta assinatura de 404, diferente das outras três, e portanto mais
     * uma maneira de classificar ids pelo corpo da resposta.
     *
     * O catch amplo também sumiu. Ele existia para o `findOrFail` de uma versão
     * anterior; hoje quem resolve o modelo é o route model binding, que já
     * devolve 404 antes do método rodar. O que sobrava dele era pior do que
     * nada: engolia a QueryException do `delete()` e respondia "não encontrado"
     * para uma falha real de banco — com a linha intacta e o usuário
     * convencido de que a remoção deu certo.
     *
     * Fica só o catch de ValidationException, que não é tratamento de erro e
     * sim o formato `{erros: {campo: [...]}}` que o frontend lê.
     */
    public function update(Request $request, TarefaPadrao $tarefaPadrao)
    {
        $this->authorize('update', $tarefaPadrao);

        try {
            $validated = $request->validate([
                'titulo'    => 'sometimes|string|max:255',
                'anotacao'  => 'nullable|string',
                'prazo_dias' => 'sometimes|integer|min:0',
            ]);

            $tarefaPadrao->update($validated);

            return response()->json(['message' => 'Modelo atualizado com sucesso', 'data' => $tarefaPadrao], 200);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json(['erros' => $error->errors()], 422);
        }
    }

    public function destroy(TarefaPadrao $tarefaPadrao)
    {
        $this->authorize('delete', $tarefaPadrao);

        $tarefaPadrao->delete();

        return response()->json(['message' => 'Modelo removido com sucesso'], 200);
    }

    public function aplicar(Request $request)
    {
        try {
            // O lead vem de um campo do CORPO: o route model binding nunca o
            // vê, então nenhuma policy roda sozinha aqui. findOrFail primeiro
            // — o TenantScope já devolve 404 para id de outro tenant — e só
            // então autoriza, para que "não existe" e "não é seu" deem a
            // mesma resposta.
            $lead = Usuario::findOrFail($request->integer('usuario_id'));

            $this->authorize('update', $lead);

            $validated = $request->validate([
                'usuario_id' => [
                    'required',
                    Rule::exists('usuarios', 'id')->where('tenant_id', app(CurrentTenant::class)->id()),
                ],
                'padroes'    => 'required|array|min:1',
                // Mesmo filtro de tenant do `usuario_id` acima. Sem ele esta
                // era a única regra `exists` da base sem `where('tenant_id')`:
                // um id de modelo de OUTRA empresa passava na validação e
                // devolvia 201, enquanto um id inexistente devolvia 422 — a
                // diferença responde "esta linha existe em alguma empresa?".
                // O `whereIn(...)->where('user_id', auth()->id())` logo abaixo
                // já impedia a criação da tarefa, então o vazamento era só de
                // informação — e era o suficiente.
                'padroes.*'  => [
                    'integer',
                    Rule::exists('tarefa_padroes', 'id')->where('tenant_id', app(CurrentTenant::class)->id()),
                ],
            ]);

            $padroes = TarefaPadrao::whereIn('id', $validated['padroes'])
                ->where('user_id', auth()->id())
                ->get();

            $criadas = [];
            foreach ($padroes as $p) {
                $criadas[] = Tarefa::create([
                    'usuario_id'  => $lead->id,
                    'titulo'      => $p->titulo,
                    'anotacao'    => $p->anotacao,
                    'data_limite' => Carbon::today()->addDays($p->prazo_dias),
                ]);
            }

            return response()->json(['message' => count($criadas) . ' tarefa(s) criada(s)', 'data' => $criadas], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json(['erros' => $error->errors()], 422);
        }
    }
}
