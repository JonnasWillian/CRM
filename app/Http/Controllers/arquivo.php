<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ArquivoRequest;
use App\Services\Arquivos\ArquivoService;
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
        // `usuario_id` (id do LEAD), por query string: GET /api/arquivos?usuario_id=.
        // Era `user_id` no corpo de um POST, nome que sugeria o agente.
        $lead = Usuario::findOrFail($request->integer('usuario_id'));

        $this->authorize('view', $lead);

        return response()->json(ArquivoModel::where('usuario_id', $lead->id)->get());
    }

    public function store(ArquivoRequest $request)
    {
        // O id que vai para o banco é o do model que ArquivoRequest::authorize()
        // já resolveu e autorizou (via integer()), não o texto cru do corpo.
        $lead = Usuario::findOrFail($request->integer('usuario_id'));

        $arquivoSalvo = $this->arquivoService->guardarComRegistro(
            $request->file('arquivo'),
            ArquivoService::pastaDoLead($lead->tenant_id, $lead->id),
            // input() e não validated(): FormRequestsTest chama este método
            // direto, sem passar $request pelo ciclo de validação (só o
            // authorize() resolvido manualmente) — validated() lançaria
            // "member function validated() on null" nesse caminho. O valor
            // já chega validado (nullable|string|max:255) em toda chamada
            // real via HTTP, então não perde a garantia.
            fn (array $dados) => ArquivoModel::create([
                ...$dados,
                'nome' => $request->input('nome') ?: '',
                'usuario_id' => $lead->id,
            ]),
        );

        return response()->json([
            'success' => true,
            'data' => $arquivoSalvo,
        ]);
    }

    public function destroy(ArquivoModel $arquivo)
    {
        $this->authorize('delete', $arquivo);

        $arquivo->delete();

        return response()->json(['message' => 'Arquivo removido']);
    }
}
