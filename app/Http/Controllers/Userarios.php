<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UsuarioRequest;
use Inertia\Inertia;

use App\Models\Usuario;
use App\Models\Anotacao;

class Userarios extends Controller
{
    public function view(Request $request)
    {
        $usuarios = Usuario::where('user_id', $request->user_id)->get();
        
        return response()->json($usuarios);
    }

    public function create(UsuarioRequest $request)
    {
        try {
            Usuario::create($request->validated());

            return response()->json(['message' => 'Usuário cadastrado com sucesso'], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        }
    }

    public function viewUsuario($id)
    {
        $usuario = Usuario::where('id', $id)->get();

        return response()->json($usuario);
    }

    public function update(UsuarioRequest $request, $id)
    {
        try {
            $usuario = Usuario::findOrFail($id);

            $usuario->update($request->validated());

            return response()->json(['message' => 'Usuário atualizado com sucesso'], 200);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Usuário não encontrado'], 404);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $usuario = Usuario::findOrFail($id);
            $usuario->delete();

            return response()->json(['message' => 'Usuário deletado com sucesso'], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Usuário não encontrado'], 201);
        }
    }

    public function viewAnotacao($id)
    {
        $anotacoes = Anotacao::where('usuario_id', $id)->get();

        return response()->json($anotacoes);
    }


    public function createAnotacao(Request $request)
    {
        try {
            $validateRequest = $request->validate([
                'descricao' => 'required|string',
                'usuario_id' => 'required',
            ]);

            Anotacao::create($validateRequest);

            return response()->json(['message' => 'Anotação cadastrada com sucesso'], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        }
    }


    public function updateAnotacao(Request $request, $id)
    {
        try {
            $anotacao = Anotacao::findOrFail($id);
            
            $validateRequest = $request->validate([
                'descricao' => 'required|string',
            ]);

            $anotacao->update($validateRequest);

            return response()->json(['message' => 'Anotação atualizada com sucesso'], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Anotação não encontrado'], 201);
        }
    }

    public function destroyAnotacao(Request $request, $id)
    {
        try {
            $anotacao = Anotacao::findOrFail($id);
            $anotacao->delete();

            return response()->json(['message' => 'Anotação deletada com sucesso'], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Anotação não encontrada'], 201);
        }
    }
}
