<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\MoverEstagioEmMassaRequest;
use App\Http\Requests\Leads\SelecaoDeLeadsRequest;
use App\Http\Requests\Leads\TransferirEmMassaRequest;
use App\Http\Requests\ListagemDeLeadsRequest;
use App\Http\Requests\UsuarioRequest;
use App\Models\Estagio;
use App\Models\Funil;
use App\Models\User;
use App\Models\Usuario;
use App\Queries\Leads\ListagemDeLeads;
use App\Services\Funis\MoverLeadDeFunil;
use App\Services\Leads\AcoesEmMassa;
use App\Services\Leads\CadastrarLead;
use App\Services\Leads\TransferirLeads;
use App\Services\Perdas\AplicarTransicao;
use App\Support\Csv\CelulaSegura;
use App\Support\Equipe\RegrasDeAdmin;
use App\Support\Listagem\Paginacao;
use App\Support\Perdas\RegrasDePerda;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Leads (tabela `usuarios`): grade, cadastro, perfil, edição, lixeira e as
 * transições de estágio e funil. Erros de validação saem no formato padrão
 * do framework (`{ message, errors }`); perda sem motivo também, via
 * AplicarTransicao.
 */
class LeadController extends Controller
{
    public function index(ListagemDeLeadsRequest $request, ListagemDeLeads $listagem): JsonResponse
    {
        $ordenacao = $request->ordenacao();
        $filtros = $request->filtrosValidados();

        return response()->json(Paginacao::resposta(
            $listagem($request->user(), $filtros, $ordenacao, $request->porPagina()),
            $ordenacao,
            $filtros,
        ));
    }

    /**
     * CSV da grade atual (filtros e ordem da URL) ou dos `ids[]` escolhidos.
     * Streaming em lotes por id: memória constante para qualquer tamanho.
     * BOM + `;` + vírgula decimal: abre direto no Excel em português; os
     * cabeçalhos são os mesmos que a importação reconhece (ida e volta).
     */
    public function exportar(ListagemDeLeadsRequest $request, ListagemDeLeads $listagem): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $ids = array_filter(array_map('intval', (array) $request->input('ids', [])));
        $q = $ids !== []
            ? Usuario::visibleTo($request->user())->whereIn('usuarios.id', array_slice($ids, 0, 1000))->with(['estagio', 'responsavel:id,name'])
            : $listagem->query($request->user(), $request->filtrosValidados())->reorder();
        $q->with('funil:id,nome');
        $decimal = fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', '');

        return response()->streamDownload(function () use ($q, $decimal) {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, ['nome', 'email', 'telefone', 'descricao', 'funil', 'estagio', 'valor_estimado', 'probabilidade', 'previsao_fechamento', 'responsavel', 'criado_em', 'ultimo_contato'], ';', '"', '');
            foreach ($q->lazyById(500, 'usuarios.id', 'id') as $l) {
                fputcsv($saida, CelulaSegura::linha([
                    $l->nome, $l->email, $l->telefone, $l->descricao, $l->funil?->nome, $l->estagio?->descricao,
                    $decimal($l->valor_estimado), $l->probabilidade, $l->previsao_fechamento?->toDateString(), $l->responsavel?->name,
                    $l->created_at?->format('Y-m-d H:i'), $l->ultimo_contato_em?->format('Y-m-d H:i'),
                ]), ';', '"', '');
            }
            fclose($saida);
        }, 'leads-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Quick-add do Pipeline e outros clientes JSON. O modal da grade usa a rota web (`leads.store`). */
    public function store(UsuarioRequest $request, CadastrarLead $cadastrar): JsonResponse
    {
        $lead = $cadastrar($request->validated(), RegrasDePerda::extrair($request), $request->user());

        return response()->json(['message' => 'Lead cadastrado.', 'data' => $lead->fresh(['estagio', 'funil'])], 201);
    }

    /**
     * Mover N leads de estágio (ids ou todos os resultados do filtro).
     * Leads de outro funil, já no destino ou fora da carteira são ignorados
     * e contados; destino perdido exige um motivo para todos (validado no
     * request, antes de mover qualquer um).
     */
    public function moverEstagio(MoverEstagioEmMassaRequest $request, AcoesEmMassa $acoes): JsonResponse
    {
        $destino = $request->destino();
        $resultado = $acoes->moverEstagio($request->selecao(), $request->tamanhoPedido(), $destino, RegrasDePerda::extrair($request));

        return response()->json(['message' => self::frase($resultado['afetados'], "movido para “{$destino->descricao}”", "movidos para “{$destino->descricao}”"), ...$resultado]);
    }

    /** Transferir um lead (perfil, grade, card). */
    public function responsavel(Request $request, TransferirLeads $transferir, Usuario $usuario): JsonResponse
    {
        $this->authorize('transferir', $usuario);

        $validated = $request->validate(
            ['user_id' => ['required', 'integer', RegrasDeAdmin::regraDeMembroAtivo(app(CurrentTenant::class)->id())]],
            ['user_id.exists' => 'Escolha um membro ativo da empresa.', 'user_id.required' => 'Escolha para quem transferir.'],
        );
        $destino = User::findOrFail($validated['user_id']);

        $mudou = $transferir->umLead($usuario, $destino);

        return response()->json([
            'message' => $mudou ? "“{$usuario->nome}” transferido para “{$destino->name}”." : "{$destino->name} já é o responsável.",
            'data' => self::comResumo($usuario->fresh()),
        ]);
    }

    public function transferir(TransferirEmMassaRequest $request, TransferirLeads $transferir): JsonResponse
    {
        $destino = $request->destino();
        $resultado = $transferir->varios($request->selecao(), $request->tamanhoPedido(), $destino);

        return response()->json(['message' => self::frase($resultado['afetados'], "transferido para “{$destino->name}”", "transferidos para “{$destino->name}”"), ...$resultado]);
    }

    public function lixeira(SelecaoDeLeadsRequest $request, AcoesEmMassa $acoes): JsonResponse
    {
        $resultado = $acoes->lixeira($request->selecao(), $request->tamanhoPedido());

        return response()->json(['message' => self::frase($resultado['afetados'], 'movido para a lixeira', 'movidos para a lixeira'), ...$resultado]);
    }

    private static function frase(int $n, string $singular, string $plural): string
    {
        return $n === 1 ? "1 lead {$singular}." : number_format($n, 0, ',', '.')." leads {$plural}.";
    }

    /** O lead com estágio, funil, responsável e os contadores do cabeçalho do perfil. */
    public function show(Usuario $usuario): JsonResponse
    {
        $this->authorize('view', $usuario);

        return response()->json(self::comResumo($usuario));
    }

    public static function comResumo(Usuario $usuario): Usuario
    {
        return $usuario
            ->load(['estagio', 'funil', 'responsavel:id,name'])
            ->loadCount([
                'anotacoes',
                'arquivos',
                'projetos',
                'projetos as projetos_abertos_count' => fn ($q) => $q->whereHas('status', fn ($s) => $s->open()),
                'tarefas as tarefas_pendentes_count' => fn ($q) => $q->where('concluido', false),
            ])
            ->loadSum(['projetos as valor_projetos_abertos' => fn ($q) => $q->whereHas('status', fn ($s) => $s->open())], 'preco');
    }

    public function update(UsuarioRequest $request, Usuario $usuario): JsonResponse
    {
        $this->authorize('update', $usuario);

        // O histórico de estágio é gravado pelo UsuarioObserver; a perda é do
        // AplicarTransicao, que precisa ver o estado ANTERIOR ao save.
        app(AplicarTransicao::class)($usuario, $request->validated(), RegrasDePerda::extrair($request));

        return response()->json(['message' => 'Dados salvos.', 'data' => self::comResumo($usuario->fresh())]);
    }

    public function destroy(Usuario $usuario): JsonResponse
    {
        $this->authorize('delete', $usuario);

        // Soft delete: vai para a lixeira. Filhos, perdas e histórico ficam.
        $usuario->delete();

        return response()->json(['message' => 'Lead movido para a lixeira.']);
    }

    public function restaurar(Usuario $usuario): JsonResponse
    {
        $this->authorize('restore', $usuario);

        // restore() num lead ativo só reatribuiria deleted_at = null e ainda
        // dispararia `restored`, gravando um "lead_restaurado" falso.
        if (! $usuario->trashed()) {
            return response()->json(['message' => 'Este lead não está na lixeira.'], 409);
        }

        $usuario->restore();

        // O estágio pode ter sido arquivado enquanto o lead estava na lixeira:
        // ele volta, mas fica numa coluna somente-saída até ser movido.
        $aviso = $usuario->estagio?->trashed() ? 'O estágio original está arquivado; mova o lead para um estágio ativo.' : null;

        return response()->json(['message' => 'Lead restaurado.', 'aviso' => $aviso]);
    }

    /** Move dentro do funil atual (arrastar o card). Trocar de funil é `funil()`. */
    public function estagio(Request $request, Usuario $usuario): JsonResponse
    {
        $this->authorize('update', $usuario);

        $validated = $request->validate([
            'estagio_id' => [
                'required',
                Rule::exists('estagios', 'id')
                    ->where('tenant_id', app(CurrentTenant::class)->id())
                    // Um estagio_id de outro funil deixaria funil e estágio
                    // discordando — estado que nenhuma tela sabe desenhar.
                    ->where('funil_id', $usuario->funil_id),
            ],
            // Ao marcar como ganho, o diálogo manda o valor fechado junto.
            'valor_fechado' => ['nullable', 'numeric', 'min:0'],
            ...RegrasDePerda::campos(),
        ], RegrasDePerda::mensagens());

        $atributos = ['estagio_id' => $validated['estagio_id']];
        if (array_key_exists('valor_fechado', $validated)) {
            $atributos['valor_fechado'] = $validated['valor_fechado'];
        }

        app(AplicarTransicao::class)($usuario, $atributos, RegrasDePerda::extrair($request));

        return response()->json(['message' => 'Estágio atualizado.', 'data' => self::comResumo($usuario->fresh())]);
    }

    public function funil(Request $request, MoverLeadDeFunil $mover, Usuario $usuario): JsonResponse
    {
        $this->authorize('update', $usuario);

        $tenantId = app(CurrentTenant::class)->id();

        $validated = $request->validate([
            'funil_id' => ['required', Rule::exists('funis', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'estagio_id' => ['required', Rule::exists('estagios', 'id')->where('tenant_id', $tenantId)],
            ...RegrasDePerda::campos(),
        ], RegrasDePerda::mensagens());

        $mover(
            $usuario,
            Funil::findOrFail($validated['funil_id']),
            Estagio::findOrFail($validated['estagio_id']),
            RegrasDePerda::extrair($request),
        );

        return response()->json(['message' => 'Lead movido de funil.', 'data' => self::comResumo($usuario->fresh())]);
    }
}
