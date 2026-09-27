<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ArquivoRequest;
use App\Service\ArquivoService;
use App\Models\arquivo AS ArquivoModel;
use App\Models\Usuario;

class arquivo extends Controller
{
    protected $arquivoService;

    public function __construct(ArquivoService $arquivoService){
        $this->arquivoService = $arquivoService;
    }

    public function index(Request $request)
    {
        // Resolver o lead e autorizar ANTES de consultar: o `usuario_id` vinha
        // do CORPO da requisição sem verificação nenhuma — nem era preciso
        // adivinhar um id, bastava enviar qualquer um.
        //
        // findOrFail e não uma regra `exists` de validação: o TenantScope já
        // devolve 404 para id de outro tenant, e as duas respostas convergem.
        // Uma regra de validação daria 422 para "não existe" e 404 para
        // "existe mas não é seu" — a diferença revelaria quais ids existem.
        //
        // Chave do payload é `user_id`, não `usuario_id`: é o nome que o
        // frontend (Perfil.vue::buscarAnexo) já envia e que o código anterior
        // já lia (`$request->user_id`). Usar `usuario_id` aqui devolveria 404
        // pra toda chamada legítima da tela de perfil do lead.
        $lead = Usuario::findOrFail($request->integer('user_id'));

        $this->authorize('view', $lead);

        return response()->json(ArquivoModel::where('usuario_id', $lead->id)->get());
    }

    public function store(ArquivoRequest $request)
    {
        // O id que vai para o banco é o do model que ArquivoRequest::authorize()
        // já resolveu e autorizou (via integer()), não o texto cru do corpo —
        // que pode trazer lixo depois do número ("1abc") e quebrar o INSERT em
        // sql_mode estrito, ou simplesmente divergir do que foi checado.
        $lead = Usuario::findOrFail($request->integer('usuario_id'));

        $caminho = $this->arquivoService->salveFile($request->file('arquivo'));

        $arquivoSalvo = ArquivoModel::create([
            'nome' => $request->nome ?: '',
            'local' => $caminho,
            'usuario_id' => $lead->id
        ]);

        return response()->json([
            'success' => true,
            'data' => $arquivoSalvo,
            'Arquivo salvo:'  => $caminho
        ]);
    }

    public function destroy(ArquivoModel $arquivo)
    {
        $this->authorize('delete', $arquivo);

        $arquivo->delete();

        return response()->json(['message' => 'Arquivo removido']);
    }
}
