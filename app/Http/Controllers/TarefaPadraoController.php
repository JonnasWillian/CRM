<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TarefaPadrao;
use App\Models\Tarefa;
use Carbon\Carbon;

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

    public function update(Request $request, $id)
    {
        try {
            $padrao = TarefaPadrao::findOrFail($id);

            $validated = $request->validate([
                'titulo'    => 'sometimes|string|max:255',
                'anotacao'  => 'nullable|string',
                'prazo_dias' => 'sometimes|integer|min:0',
            ]);

            $padrao->update($validated);

            return response()->json(['message' => 'Modelo atualizado com sucesso', 'data' => $padrao], 200);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json(['erros' => $error->errors()], 422);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Modelo não encontrado'], 404);
        }
    }

    public function destroy($id)
    {
        try {
            $padrao = TarefaPadrao::findOrFail($id);
            $padrao->delete();

            return response()->json(['message' => 'Modelo removido com sucesso'], 200);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Modelo não encontrado'], 404);
        }
    }

    public function aplicar(Request $request)
    {
        try {
            $validated = $request->validate([
                'usuario_id' => 'required|exists:usuarios,id',
                'padroes'    => 'required|array|min:1',
                'padroes.*'  => 'integer|exists:tarefa_padroes,id',
            ]);

            $padroes = TarefaPadrao::whereIn('id', $validated['padroes'])
                ->where('user_id', auth()->id())
                ->get();

            $criadas = [];
            foreach ($padroes as $p) {
                $criadas[] = Tarefa::create([
                    'usuario_id'  => $validated['usuario_id'],
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
