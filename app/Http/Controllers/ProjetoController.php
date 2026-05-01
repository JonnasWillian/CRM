<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ProjetoRequest;
use App\Models\Statu;
use App\Models\Projeto;

class ProjetoController extends Controller
{
    public function getStatus()
    {
        $status = Statu::get();
        
        return response()->json($status);
    }

    public function view(Request $request)
    {
        $projetos = Projeto::where('usuario_id', $request->usuario_id)->with('status')->get();
        
        return response()->json($projetos);
    }

    public function create(ProjetoRequest $request)
    {
        try {
            Projeto::create($request->validated());

            return response()->json(['message' => 'Projeto cadastrado com sucesso'], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        }
    }

    public function viewProjeto($id)
    {
        $projeto = ProjetoRequest::where('id', $id)->get();

        return response()->json($projeto);
    }

    public function update(ProjetoRequest $request, $id)
    {
        try {
            $projeto = Projeto::findOrFail($id);

            $projeto->update($request->validated());

            return response()->json(['message' => 'Projeto atualizado com sucesso'], 200);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        }
    }
}
