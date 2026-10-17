<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListagemDeLeadsRequest;
use App\Http\Requests\UsuarioRequest;
use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Usuario;
use App\Queries\Leads\ListagemDeLeads;
use App\Services\Funis\MoverLeadDeFunil;
use App\Services\Perdas\AplicarTransicao;
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

    public function store(UsuarioRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = auth()->id();

        if (empty($validated['funil_id'])) {
            $validated['funil_id'] = Funil::padrao()?->id;
        }

        if (empty($validated['estagio_id']) && ! empty($validated['funil_id'])) {
            $validated['estagio_id'] = Estagio::where('funil_id', $validated['funil_id'])
                ->aberto()
                ->orderBy('ordem')
                ->orderBy('id')
                ->value('id');
        }

        // Via AplicarTransicao e não Usuario::create(): o quick-add do Kanban
        // cadastra o lead direto na coluna clicada, e essa coluna pode ser
        // um estágio perdido. Nascer perdido é perder.
        $lead = new Usuario;
        app(AplicarTransicao::class)($lead, $validated, RegrasDePerda::extrair($request));

        return response()->json(['message' => 'Lead cadastrado.', 'data' => $lead->fresh(['estagio', 'funil'])], 201);
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

        return response()->json(['message' => 'Lead restaurado.']);
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
