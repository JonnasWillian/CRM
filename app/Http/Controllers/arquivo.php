<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ArquivoRequest;
use App\Service\ArquivoService;
use App\Models\arquivo AS ArquivoModel;

class arquivo extends Controller
{
    protected $arquivoService;

    public function __construct(ArquivoService $arquivoService){
        $this->arquivoService = $arquivoService;
    }

    public function index(Request $request)
    {
        $arquivos = ArquivoModel::where('usuario_id', $request->user_id)->get();

        return response()->json($arquivos);
    }

    public function store(ArquivoRequest $request)
    {
        $caminho = $this->arquivoService->salveFile($request->file('arquivo'));

        $arquivoSalvo = ArquivoModel::create([
            'nome' => $request->nome ?: '',
            'local' => $caminho,
            'usuario_id' => $request->usuario_id
        ]);

        return response()->json([
            'success' => true,
            'data' => $arquivoSalvo,
            'Arquivo salvo:'  => $caminho
        ]);
    }

    public function show(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        try {
            $arquivo = ArquivoModel::findOrFail($id);
            $arquivo->delete();

            return response()->json(['message' => 'Arquivo deletado com sucesso'], 200);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Arquivo não encontrado'], 404);
        }
    }
}
