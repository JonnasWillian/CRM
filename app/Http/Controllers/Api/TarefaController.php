<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListagemDeTarefasRequest;
use App\Models\Tarefa;
use App\Models\Usuario;
use App\Support\Listagem\Paginacao;
use Illuminate\Http\Request;

class TarefaController extends Controller
{
    /** Tarefas de um lead (aba Tarefas): pendentes por vencimento, concluídas por conclusão. */
    public function porLead(Request $request, Usuario $usuario)
    {
        $this->authorize('view', $usuario);

        $escopo = $request->input('escopo', 'todas');

        $pagina = Tarefa::where('usuario_id', $usuario->id)
            ->when($escopo === 'pendentes', fn ($q) => $q->where('concluido', false)->orderBy('data_limite')->orderBy('id'))
            ->when($escopo === 'concluidas', fn ($q) => $q->where('concluido', true)->orderByDesc('concluido_em')->orderByDesc('id'))
            ->when($escopo === 'todas', fn ($q) => $q->orderBy('concluido')->orderBy('data_limite')->orderBy('id'))
            ->paginate(Paginacao::clamp($request->input('per_page'), 50))
            ->withQueryString();

        return response()->json(Paginacao::resposta($pagina, null, ['escopo' => $escopo]));
    }

    public function store(Request $request, Usuario $usuario)
    {
        $this->authorize('update', $usuario);

        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'data_limite' => 'required|date',
            'anotacao' => 'nullable|string',
        ]);

        $tarefa = Tarefa::create([...$validated, 'usuario_id' => $usuario->id]);

        return response()->json(['message' => 'Tarefa salva.', 'data' => $tarefa], 201);
    }

    public function update(Request $request, Tarefa $tarefa)
    {
        $this->authorize('update', $tarefa);

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

        return response()->json(['message' => 'Tarefa salva.', 'data' => $tarefa->fresh()]);
    }

    public function destroy(Tarefa $tarefa)
    {
        $this->authorize('delete', $tarefa);

        $tarefa->delete();

        return response()->json(['message' => 'Tarefa excluída.']);
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
