<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\ProjetoRequest;
use App\Http\Requests\ProjetoAnexoRequest;
use App\Support\LimitesDeTexto;

use App\Services\Arquivos\ArquivoService;

use App\Models\Statu;
use App\Models\Projeto;
use App\Models\ProjetoAnotacao;
use App\Models\ProjetoAnexo;
use App\Models\Usuario;
use App\Services\Perdas\AplicarTransicao;
use App\Support\Perdas\RegrasDePerda;

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
        // O lead vem de um campo do CORPO: o binding nunca o enxerga, então a
        // policy não roda sozinha. findOrFail primeiro — o TenantScope já
        // devolve 404 para id de outro tenant — e só então autoriza, para que
        // "não existe" e "não é seu" deem a mesma resposta.
        $lead = Usuario::findOrFail($request->integer('usuario_id'));

        $this->authorize('view', $lead);

        return response()->json(
            Projeto::where('usuario_id', $lead->id)->with('status')->get()
        );
    }


    public function create(ProjetoRequest $request)
    {
        try {
            // Um projeto pode nascer já perdido — o status é escolhido no
            // próprio formulário de cadastro.
            app(AplicarTransicao::class)(new Projeto(), $request->validated(), RegrasDePerda::extrair($request));

            return response()->json(['message' => 'Projeto cadastrado com sucesso'], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        }
    }


    public function viewProjeto(Projeto $projeto)
    {
        $this->authorize('view', $projeto);

        return response()->json($projeto->load('status'));
    }


    public function update(ProjetoRequest $request, Projeto $projeto)
    {
        $this->authorize('update', $projeto);

        try {
            app(AplicarTransicao::class)($projeto, $request->validated(), RegrasDePerda::extrair($request));

            return response()->json(['message' => 'Projeto atualizado com sucesso'], 200);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        }
    }


    public function viewAnotacao(Projeto $projeto)
    {
        $this->authorize('view', $projeto);

        $anotacoes = ProjetoAnotacao::where('projeto_id', $projeto->id)->get();

        return response()->json($anotacoes);
    }


    public function createAnotacao(Request $request)
    {
        try {
            // O projeto vem de um campo do CORPO: o binding nunca o enxerga,
            // então a policy não roda sozinha. findOrFail primeiro — o
            // TenantScope já devolve 404 para id de outro tenant — e só então
            // autoriza, para que "não existe" e "não é seu" deem a mesma
            // resposta.
            $projeto = Projeto::findOrFail($request->integer('projeto_id'));

            $this->authorize('update', $projeto);

            $validated = $request->validate(['descricao' => ['required', 'string', 'max:'.LimitesDeTexto::ANOTACAO]]);

            ProjetoAnotacao::create([
                'descricao' => $validated['descricao'],
                'projeto_id' => $projeto->id,
            ]);

            return response()->json(['message' => 'Anotação cadastrada com sucesso'], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        }
    }


    public function updateAnotacao(Request $request, ProjetoAnotacao $projetoAnotacao)
    {
        $this->authorize('update', $projetoAnotacao);

        try {
            $validateRequest = $request->validate([
                'descricao' => ['required', 'string', 'max:'.LimitesDeTexto::ANOTACAO],
            ]);

            $projetoAnotacao->update($validateRequest);

            return response()->json(['message' => 'Anotação atualizada com sucesso'], 200);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        }
    }


    public function destroyAnotacao(ProjetoAnotacao $projetoAnotacao)
    {
        $this->authorize('delete', $projetoAnotacao);

        $projetoAnotacao->delete();

        return response()->json(['message' => 'Anotação deletada com sucesso'], 200);
    }


    public function viewAnexo(Projeto $projeto)
    {
        $this->authorize('view', $projeto);

        $anexos = ProjetoAnexo::where('projeto_id', $projeto->id)->get();

        return response()->json($anexos);
    }


    public function createAnexo(ProjetoAnexoRequest $request)
    {
        // Mesmo motivo do irmão arquivo::store(): o id é o que
        // ProjetoAnexoRequest::authorize() já resolveu e autorizou.
        $projeto = Projeto::findOrFail($request->integer('usuario_id'));

        $anexo = $this->arquivoService->guardarComRegistro(
            $request->file('arquivo'),
            ArquivoService::pastaDoProjeto($projeto->tenant_id, $projeto->id),
            // input() e não validated(): mesmo motivo do irmão
            // arquivo::store() — FormRequestsTest chama este método direto,
            // sem passar $request pelo ciclo de validação.
            fn (array $dados) => ProjetoAnexo::create([
                ...$dados,
                'nome' => $request->input('nome') ?: '',
                'projeto_id' => $projeto->id,
            ]),
        );

        return response()->json([
            'success' => true,
            'data' => $anexo,
        ], 201);
    }


    public function destroyAnexo(ProjetoAnexo $projetoAnexo)
    {
        $this->authorize('delete', $projetoAnexo);

        $projetoAnexo->delete();

        return response()->json(['message' => 'Anexo deletado com sucesso'], 200);
    }

    public function destroy(Projeto $projeto)
    {
        $this->authorize('delete', $projeto);

        $projeto->delete();

        return response()->json(['message' => 'Projeto movido para a lixeira']);
    }

    public function restaurar(Projeto $projeto)
    {
        $this->authorize('restore', $projeto);

        // restore() não verifica se o registro estava na lixeira: chamado
        // num projeto ativo, ele só reatribui deleted_at = null (sem efeito)
        // e ainda assim dispara o evento `restored`, gravando um
        // "projeto_restaurado" falso no histórico.
        if (! $projeto->trashed()) {
            return response()->json(['message' => 'Este projeto não está na lixeira.'], 409);
        }

        $projeto->restore();

        return response()->json(['message' => 'Projeto restaurado']);
    }
}
