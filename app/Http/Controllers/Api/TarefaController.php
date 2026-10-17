<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListagemDeTarefasRequest;
use App\Http\Requests\Tarefas\SelecaoDeTarefasRequest;
use App\Models\Tarefa;
use App\Models\Usuario;
use App\Queries\Tarefas\ListagemDeTarefas;
use App\Queries\Tarefas\ResumoDeTarefas;
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

    /** Lista global (página Tarefas): ver ListagemDeTarefas. */
    public function listar(ListagemDeTarefasRequest $request, ListagemDeTarefas $listagem)
    {
        $ordenacao = $request->ordenacao();
        $filtros = ['escopo' => ListagemDeTarefas::ESCOPO_PADRAO, ...$request->filtrosValidados()];

        return response()->json(Paginacao::resposta($listagem($request->user(), $filtros, $ordenacao, $request->porPagina()), $ordenacao, $filtros));
    }

    /** Resumo para o dashboard (ver ResumoDeTarefas). */
    public function resumo(Request $request, ResumoDeTarefas $resumo)
    {
        return response()->json($resumo($request->user()));
    }

    /** Concluir em massa: cada tarefa salva pelo model (observer registra a atividade). */
    public function concluir(SelecaoDeTarefasRequest $request)
    {
        $pedidas = count($request->ids());
        $afetadas = 0;

        $request->selecao()->where('concluido', false)->chunkById(100, function ($tarefas) use (&$afetadas) {
            foreach ($tarefas as $tarefa) {
                $tarefa->update(['concluido' => true, 'concluido_em' => now()]);
                $afetadas++;
            }
        }, 'tarefas.id', 'id');

        return response()->json(['message' => self::frase($afetadas, 'concluída'), 'afetados' => $afetadas, 'ignorados' => max(0, $pedidas - $afetadas)]);
    }

    public function excluir(SelecaoDeTarefasRequest $request)
    {
        $pedidas = count($request->ids());
        $afetadas = 0;

        $request->selecao()->chunkById(100, function ($tarefas) use (&$afetadas) {
            foreach ($tarefas as $tarefa) {
                $tarefa->delete();
                $afetadas++;
            }
        }, 'tarefas.id', 'id');

        return response()->json(['message' => self::frase($afetadas, 'excluída'), 'afetados' => $afetadas, 'ignorados' => max(0, $pedidas - $afetadas)]);
    }

    private static function frase(int $n, string $acao): string
    {
        return $n === 1 ? "1 tarefa {$acao}." : "{$n} tarefas {$acao}s.";
    }
}
