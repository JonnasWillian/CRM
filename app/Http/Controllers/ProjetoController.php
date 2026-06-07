<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ProjetoRequest;
use App\Http\Requests\ArquivoRequest;

use App\Service\ArquivoService;

use App\Models\Statu;
use App\Models\Projeto;
use App\Models\ProjetoAnotacao;
use App\Models\ProjetoAnexo;

class ProjetoController extends Controller
{
    protected $arquivoService;

    public function __construct(ArquivoService $arquivoService){
        $this->arquivoService = $arquivoService;
    }


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
        $projeto = Projeto::where('id', $id)->with('status')->first();

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
        } catch (\Exception $error) {
            return response()->json(['error' => 'Projeto não encontrado'], 404);
        }
    }


    public function viewAnotacao($id)
    {
        $anotacoes = ProjetoAnotacao::where('projeto_id', $id)->get();

        return response()->json($anotacoes);
    }


    public function createAnotacao(Request $request)
    {
        try {
            $validateRequest = $request->validate([
                'descricao' => 'required|string',
                'projeto_id' => 'required|exists:projetos,id',
            ]);

            ProjetoAnotacao::create($validateRequest);

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
            $anotacao = ProjetoAnotacao::findOrFail($id);

            $validateRequest = $request->validate([
                'descricao' => 'required|string',
            ]);

            $anotacao->update($validateRequest);

            return response()->json(['message' => 'Anotação atualizada com sucesso'], 200);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Anotação não encontrada'], 404);
        }
    }


    public function destroyAnotacao($id)
    {
        try {
            $anotacao = ProjetoAnotacao::findOrFail($id);
            $anotacao->delete();

            return response()->json(['message' => 'Anotação deletada com sucesso'], 200);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Anotação não encontrada'], 404);
        }
    }


    public function viewAnexo($id)
    {
        $anexos = ProjetoAnexo::where('projeto_id', $id)->get();

        return response()->json($anexos);
    }


    public function createAnexo(ArquivoRequest $request)
    {
        try {
            $caminho = $this->arquivoService->salveFile($request->file('arquivo'));

            $anexo = ProjetoAnexo::create([
                'nome' => $request->nome ?: '',
                'local' => $caminho,
                'projeto_id' => $request->usuario_id,
            ]);

            return response()->json([
                'success' => true,
                'data' => $anexo,
            ], 201);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Erro ao salvar anexo'], 500);
        }
    }


    public function destroyAnexo($id)
    {
        try {
            $anexo = ProjetoAnexo::findOrFail($id);
            $anexo->delete();

            return response()->json(['message' => 'Anexo deletado com sucesso'], 200);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Anexo não encontrado'], 404);
        }
    }
}
