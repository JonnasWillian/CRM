<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListagemDeTarefasRequest;
use App\Models\Tarefa;
use App\Models\Usuario;
use App\Support\Listagem\Paginacao;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TarefaController extends Controller
{
    public function index(Usuario $usuario)
    {
        $this->authorize('view', $usuario);

        $tarefas = Tarefa::where('usuario_id', $usuario->id)
            ->orderBy('concluido')
            ->orderBy('data_limite')
            ->get();

        return response()->json($tarefas);
    }

    public function store(Request $request)
    {
        try {
            // O lead vem de um campo do CORPO: o route model binding nunca o vê,
            // então a TarefaPolicy não roda sozinha aqui. findOrFail primeiro — o
            // TenantScope já devolve 404 para id de outro tenant — e só então
            // autoriza, para que "não existe" e "não é seu" deem a mesma resposta.
            $lead = Usuario::findOrFail($request->integer('usuario_id'));

            $this->authorize('update', $lead);

            $validated = $request->validate([
                'usuario_id' => [
                    'required',
                    Rule::exists('usuarios', 'id')->where('tenant_id', app(CurrentTenant::class)->id()),
                ],
                'titulo' => 'required|string|max:255',
                'data_limite' => 'required|date',
                'anotacao' => 'nullable|string',
            ]);

            $validated['usuario_id'] = $lead->id;

            $tarefa = Tarefa::create($validated);

            return response()->json(['message' => 'Tarefa criada com sucesso', 'data' => $tarefa], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json(['erros' => $error->errors()], 422);
        }
    }

    public function update(Request $request, Tarefa $tarefa)
    {
        $this->authorize('update', $tarefa);

        try {
            $validated = $request->validate([
                'titulo' => 'sometimes|string|max:255',
                'data_limite' => 'sometimes|date',
                'anotacao' => 'nullable|string',
                'concluido' => 'sometimes|boolean',
            ]);

            if (isset($validated['concluido'])) {
                $validated['concluido_em'] = $validated['concluido'] ? now() : null;
            }

            $tarefa->update($validated);

            return response()->json(['message' => 'Tarefa atualizada com sucesso', 'data' => $tarefa], 200);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json(['erros' => $error->errors()], 422);
        }
    }

    public function destroy(Tarefa $tarefa)
    {
        $this->authorize('delete', $tarefa);

        $tarefa->delete();

        return response()->json(['message' => 'Tarefa removida com sucesso'], 200);
    }

    /**
     * Lista global (página Tarefas): tarefas dos leads que o agente enxerga,
     * por escopo de vencimento, paginada e ordenável. Comparações de data
     * por valor (`DATE` com `DATE`), não `whereDate()`, para usar o índice.
     */
    public function listar(ListagemDeTarefasRequest $request)
    {
        $user = $request->user();
        $filtros = $request->filtrosValidados();
        $ordenacao = $request->ordenacao();
        $hoje = today()->toDateString();

        $visiveis = Usuario::idsVisiveisPara($user)
            ->when(($filtros['user_id'] ?? null) && $user->can('leads.view-all'), fn ($q) => $q->where('usuarios.user_id', (int) $filtros['user_id']));

        $q = Tarefa::query()
            ->whereIn('tarefas.usuario_id', $visiveis)
            ->with('lead:id,nome')
            ->when($filtros['busca'] ?? null, function ($q, string $busca) {
                $termo = '%'.addcslashes($busca, '%_\\').'%';
                $q->where(fn ($w) => $w->where('tarefas.titulo', 'like', $termo)
                    ->orWhereHas('lead', fn ($l) => $l->where('usuarios.nome', 'like', $termo)));
            });

        match ($filtros['escopo'] ?? 'pendentes') {
            'atrasadas' => $q->where('concluido', false)->where('data_limite', '<', $hoje),
            'hoje' => $q->where('concluido', false)->where('data_limite', $hoje),
            'proximos_7' => $q->where('concluido', false)->whereBetween('data_limite', [$hoje, today()->addDays(7)->toDateString()]),
            'pendentes' => $q->where('concluido', false),
            'concluidas' => $q->where('concluido', true),
            default => null,
        };

        $pagina = $ordenacao->aplicar($q, 'tarefas.id')->paginate($request->porPagina())->withQueryString();

        return response()->json(Paginacao::resposta($pagina, $ordenacao, $filtros));
    }

    /**
     * Resumo para o dashboard: totais de hoje/atrasadas/próximos 7 dias e
     * os 10 primeiros de cada grupo — substitui `pendentes()`, que devolvia
     * as listas inteiras sem limite.
     */
    public function resumo(Request $request)
    {
        $visiveis = fn () => Usuario::idsVisiveisPara($request->user());
        $hoje = today();
        $pendentes = fn () => Tarefa::whereIn('usuario_id', $visiveis())->where('concluido', false);
        $itens = fn ($q) => $q->with('lead:id,nome')->orderBy('data_limite')->orderBy('id')->limit(10)->get();

        $atrasadas = $pendentes()->where('data_limite', '<', $hoje->toDateString());
        $deHoje = $pendentes()->where('data_limite', $hoje->toDateString());
        $proximos = $pendentes()->whereBetween('data_limite', [$hoje->copy()->addDay()->toDateString(), $hoje->copy()->addDays(7)->toDateString()]);

        return response()->json([
            'atrasadas' => ['total' => (clone $atrasadas)->count(), 'itens' => $itens($atrasadas)],
            'hoje' => ['total' => (clone $deHoje)->count(), 'itens' => $itens($deHoje)],
            'proximos_7_dias' => ['total' => $proximos->count()],
        ]);
    }

    public function pendentes(Request $request)
    {
        // Subconsulta, não lista: Usuario::idsVisiveisPara devolve um Builder
        // para o banco resolver "quais leads este agente vê" dentro da
        // própria consulta, sem mandar a carteira inteira como bindings.
        $visiveis = fn () => Usuario::idsVisiveisPara(auth()->user());

        $hoje = Tarefa::whereIn('usuario_id', $visiveis())
            ->whereDate('data_limite', today())
            ->where('concluido', false)
            ->with('lead:id,nome')
            ->orderBy('data_limite')
            ->get();

        $atrasadas = Tarefa::whereIn('usuario_id', $visiveis())
            ->whereDate('data_limite', '<', today())
            ->where('concluido', false)
            ->with('lead:id,nome')
            ->orderBy('data_limite')
            ->get();

        return response()->json(['hoje' => $hoje, 'atrasadas' => $atrasadas]);
    }
}
