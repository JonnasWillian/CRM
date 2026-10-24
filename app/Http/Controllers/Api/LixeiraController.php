<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListagemDaLixeiraRequest;
use App\Http\Requests\RestaurarDaLixeiraRequest;
use App\Queries\Lixeira\ListagemDaLixeira;
use App\Support\Listagem\Paginacao;
use Illuminate\Http\JsonResponse;

class LixeiraController extends Controller
{
    public function index(ListagemDaLixeiraRequest $request, ListagemDaLixeira $lixeira): JsonResponse
    {
        return response()->json(self::resposta($request, $lixeira));
    }

    public static function resposta(ListagemDaLixeiraRequest $request, ListagemDaLixeira $lixeira): array
    {
        $ordenacao = $request->ordenacao();
        $filtros = ['tipo' => 'leads', ...$request->filtrosValidados()];

        return Paginacao::resposta($lixeira($request->user(), $filtros, $ordenacao, $request->porPagina()), $ordenacao, $filtros);
    }

    /**
     * Restaurar vários de uma vez. Só os que o agente enxerga; projeto de
     * lead que continua na lixeira é ignorado (restaure o lead primeiro).
     */
    public function restaurar(RestaurarDaLixeiraRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $ids = array_values(array_unique(array_map('intval', $dados['ids'])));

        $itens = $dados['tipo'] === 'leads'
            ? ListagemDaLixeira::leadsVisiveis($request->user())->whereIn('usuarios.id', $ids)->get()
            : ListagemDaLixeira::projetosVisiveis($request->user())->whereIn('projetos.id', $ids)->whereHas('usuario')->get();

        if ($dados['tipo'] === 'leads' && $itens->isNotEmpty()) {
            app(\App\Services\Assinaturas\Assinaturas::class)->verificarLimite($request->user()->tenant_id, 'leads', $itens->count());
        }
        $itens->each->restore();
        $afetados = $itens->count();
        $palavra = $dados['tipo'] === 'leads' ? ($afetados === 1 ? 'lead restaurado' : 'leads restaurados') : ($afetados === 1 ? 'projeto restaurado' : 'projetos restaurados');

        return response()->json(['message' => "{$afetados} {$palavra}.", 'afetados' => $afetados, 'ignorados' => count($ids) - $afetados]);
    }
}
