<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tarefa;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
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
                'titulo'     => 'required|string|max:255',
                'data_limite' => 'required|date',
                'anotacao'   => 'nullable|string',
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
                'titulo'      => 'sometimes|string|max:255',
                'data_limite' => 'sometimes|date',
                'anotacao'    => 'nullable|string',
                'concluido'   => 'sometimes|boolean',
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

    public function pendentes(Request $request)
    {
        // Subconsulta, não lista: o banco resolve "quais leads este agente vê"
        // dentro da própria consulta. pluck('id') + whereIn mandava a carteira
        // inteira como bindings.
        $visiveis = fn () => Usuario::visibleTo(auth()->user())->select('usuarios.id');

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
