<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tarefa;
use App\Models\Usuario;

class TarefaController extends Controller
{
    public function index($usuarioId)
    {
        $tarefas = Tarefa::where('usuario_id', $usuarioId)
            ->orderBy('concluido')
            ->orderBy('data_limite')
            ->get();

        return response()->json($tarefas);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'usuario_id' => 'required|exists:usuarios,id',
                'titulo'     => 'required|string|max:255',
                'data_limite' => 'required|date',
                'anotacao'   => 'nullable|string',
            ]);

            $tarefa = Tarefa::create($validated);

            return response()->json(['message' => 'Tarefa criada com sucesso', 'data' => $tarefa], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json(['erros' => $error->errors()], 422);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $tarefa = Tarefa::findOrFail($id);

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
        } catch (\Exception $error) {
            return response()->json(['error' => 'Tarefa não encontrada'], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $tarefa = Tarefa::findOrFail($id);
            $tarefa->delete();

            return response()->json(['message' => 'Tarefa removida com sucesso'], 200);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Tarefa não encontrada'], 404);
        }
    }

    public function pendentes(Request $request)
    {
        $leadIds = Usuario::where('user_id', $request->user_id)->pluck('id');

        if ($leadIds->isEmpty()) {
            return response()->json(['hoje' => [], 'atrasadas' => []]);
        }

        $hoje = Tarefa::whereIn('usuario_id', $leadIds)
            ->whereDate('data_limite', today())
            ->where('concluido', false)
            ->with('lead:id,nome')
            ->orderBy('data_limite')
            ->get();

        $atrasadas = Tarefa::whereIn('usuario_id', $leadIds)
            ->whereDate('data_limite', '<', today())
            ->where('concluido', false)
            ->with('lead:id,nome')
            ->orderBy('data_limite')
            ->get();

        return response()->json(['hoje' => $hoje, 'atrasadas' => $atrasadas]);
    }
}
