<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tarefa;
use App\Models\TarefaPadrao;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'anotacao' => 'nullable|string',
            'prazo_dias' => 'required|integer|min:0',
        ]);

        $validated['user_id'] = auth()->id();

        $padrao = TarefaPadrao::create($validated);

        return response()->json(['message' => 'Modelo salvo.', 'data' => $padrao], 201);
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

        $validated = $request->validate([
            'titulo' => 'sometimes|string|max:255',
            'anotacao' => 'nullable|string',
            'prazo_dias' => 'sometimes|integer|min:0',
        ]);

        $tarefaPadrao->update($validated);

        return response()->json(['message' => 'Modelo salvo.', 'data' => $tarefaPadrao]);
    }

    public function destroy(TarefaPadrao $tarefaPadrao)
    {
        $this->authorize('delete', $tarefaPadrao);

        $tarefaPadrao->delete();

        return response()->json(['message' => 'Modelo removido com sucesso'], 200);
    }

    /**
     * Cria, para o lead da rota, uma tarefa por modelo escolhido — ou todas,
     * ou nenhuma (transação): cada create dispara um observer que grava no
     * activity log, e uma falha no meio deixaria metade aplicada.
     */
    public function aplicar(Request $request, Usuario $usuario)
    {
        $this->authorize('update', $usuario);

        $validated = $request->validate([
            'padroes' => 'required|array|min:1',
            'padroes.*' => ['integer', Rule::exists('tarefa_padroes', 'id')->where('tenant_id', app(CurrentTenant::class)->id())],
        ]);

        $padroes = TarefaPadrao::whereIn('id', $validated['padroes'])
            ->where('user_id', auth()->id())
            ->get();

        $criadas = DB::transaction(fn () => $padroes->map(fn ($p) => Tarefa::create([
            'usuario_id' => $usuario->id,
            'titulo' => $p->titulo,
            'anotacao' => $p->anotacao,
            'data_limite' => Carbon::today()->addDays($p->prazo_dias),
        ]))->all());

        return response()->json(['message' => count($criadas).' tarefa(s) criada(s) a partir dos modelos.', 'data' => $criadas], 201);
    }
}
