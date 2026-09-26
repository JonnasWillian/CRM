<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UsuarioRequest;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

use App\Models\Usuario;
use App\Models\Estagio;
use App\Models\Funil;
use App\Services\Funis\MoverLeadDeFunil;
use App\Services\Perdas\AplicarTransicao;
use App\Support\Perdas\RegrasDePerda;
use App\Models\Anotacao;
use App\Models\arquivo AS ArquivoModel;
use App\Models\Projeto;
use App\Models\ProjetoAnotacao;
use App\Models\ProjetoAnexo;
use App\Models\EstagioHistorico;
use App\Models\Tarefa;

class Userarios extends Controller
{
    public function view(Request $request)
    {
        $usuarios = Usuario::where('user_id', auth()->id())
            ->with('estagio')
            ->addSelect([
                '*',
                'tem_projeto' => Projeto::whereColumn('usuario_id', 'usuarios.id')
                    ->selectRaw('COUNT(*) > 0'),
                'tem_projeto_aberto' => Projeto::whereColumn('usuario_id', 'usuarios.id')
                    ->whereHas('status', fn ($q) => $q->open())
                    ->selectRaw('COUNT(*) > 0'),
            ])
            ->get();

        return response()->json($usuarios);
    }

    public function estagios(Request $request)
    {
        $estagios = Estagio::query()
            ->when($request->filled('funil_id'), fn ($q) => $q->where('funil_id', $request->integer('funil_id')))
            ->orderBy('ordem')
            ->orderBy('id')
            ->get();

        return response()->json($estagios);
    }

    /**
     * Funil em que o quadro/cadastro opera quando o cliente não escolhe um.
     *
     * Cai no padrão do tenant; se nenhum estiver marcado (tenant anterior à
     * feature cujo backfill não rodou, ou padrão arquivado por caminho
     * inesperado), usa o primeiro da ordem em vez de devolver nada — um Kanban
     * vazio sem explicação é pior que um Kanban no funil errado.
     */
    private function funilPadrao(): ?Funil
    {
        return Funil::where('is_default', true)->first() ?? Funil::ordenados()->first();
    }


    public function create(UsuarioRequest $request)
    {
        try {
            $validated = $request->validated();
            $validated['user_id'] = auth()->id();

            // Substitui o `payload.tag_id = 1` que o Dashboard mandava fixo. Um
            // id chutado no cliente só funcionava porque o conjunto de estágios
            // era o mesmo para todo mundo; com funis por tenant ele apontaria
            // para o estágio de outra empresa ou para nada.
            if (empty($validated['funil_id'])) {
                $validated['funil_id'] = $this->funilPadrao()?->id;
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
            app(AplicarTransicao::class)(new Usuario(), $validated, RegrasDePerda::extrair($request));

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

            // O histórico de estágio é gravado pelo UsuarioObserver, que
            // observa a mudança de estagio_id em qualquer caminho de escrita.
            // A perda é do AplicarTransicao, que precisa ver o estado ANTERIOR
            // e por isso roda antes do save, não num observer.
            app(AplicarTransicao::class)($usuario, $request->validated(), RegrasDePerda::extrair($request));

            return response()->json(['message' => 'Usuário atualizado com sucesso'], 200);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Usuário não encontrado'], 404);
        }
    }

    public function timeline($id)
    {
        try {
            $usuario = Usuario::findOrFail($id);
            $events = [];

            $events[] = [
                'tipo'      => 'lead_criado',
                'descricao' => 'Lead cadastrado no sistema',
                'data'      => $usuario->created_at,
            ];

            foreach (Anotacao::where('usuario_id', $id)->get() as $a) {
                $events[] = [
                    'tipo'     => 'anotacao',
                    'descricao' => $a->descricao,
                    'data'     => $a->created_at,
                ];
            }

            foreach (ArquivoModel::where('usuario_id', $id)->get() as $f) {
                $events[] = [
                    'tipo' => 'arquivo',
                    'nome' => $f->nome ?: 'Arquivo sem nome',
                    'data' => $f->created_at,
                ];
            }

            foreach (Projeto::where('usuario_id', $id)->get() as $p) {
                $events[] = [
                    'tipo' => 'projeto',
                    'nome' => $p->nome,
                    'data' => $p->created_at,
                ];

                foreach (ProjetoAnotacao::where('projeto_id', $p->id)->get() as $pa) {
                    $events[] = [
                        'tipo'         => 'projeto_anotacao',
                        'descricao'    => $pa->descricao,
                        'projeto_nome' => $p->nome,
                        'data'         => $pa->created_at,
                    ];
                }

                foreach (ProjetoAnexo::where('projeto_id', $p->id)->get() as $pf) {
                    $events[] = [
                        'tipo'         => 'projeto_anexo',
                        'nome'         => $pf->nome ?: 'Arquivo sem nome',
                        'projeto_nome' => $p->nome,
                        'data'         => $pf->created_at,
                    ];
                }
            }

            foreach (EstagioHistorico::with(['estagioAnterior', 'estagioNovo'])->where('usuario_id', $id)->get() as $h) {
                $events[] = [
                    'tipo'          => 'status_alterado',
                    'estagio_anterior' => $h->estagioAnterior->descricao ?? '—',
                    'estagio_novo'     => $h->estagioNovo->descricao ?? '—',
                    'data'          => $h->created_at,
                ];
            }

            foreach (Tarefa::where('usuario_id', $id)->get() as $t) {
                $events[] = [
                    'tipo'   => 'tarefa_criada',
                    'titulo' => $t->titulo,
                    'data'   => $t->created_at,
                ];
                if ($t->concluido && $t->concluido_em) {
                    $events[] = [
                        'tipo'   => 'tarefa_concluida',
                        'titulo' => $t->titulo,
                        'data'   => $t->concluido_em,
                    ];
                }
            }

            usort($events, fn($a, $b) => $b['data'] <=> $a['data']);

            return response()->json($events);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Lead não encontrado'], 404);
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

    public function kanban(Request $request)
    {
        $userId = auth()->id();

        // O quadro mostra um funil por vez. Sem funil_id, o padrão do tenant.
        // Com um funil_id que não existe neste tenant, 404 — devolver o padrão
        // calado faria o usuário ver um quadro que não é o que ele pediu.
        $funil = $request->filled('funil_id')
            ? Funil::findOrFail($request->integer('funil_id'))
            : $this->funilPadrao();

        if ($funil === null) {
            return response()->json(['funil' => null, 'funis' => [], 'estagios' => [], 'leads' => []]);
        }

        $leads = Usuario::where('user_id', $userId)
            ->where('funil_id', $funil->id)
            ->addSelect([
                '*',
                'ultimo_contato' => Anotacao::whereColumn('usuario_id', 'usuarios.id')
                    ->orderByDesc('created_at')
                    ->select('created_at')
                    ->limit(1),
                'valor_projetos' => Projeto::whereColumn('usuario_id', 'usuarios.id')
                    ->whereHas('status', fn ($q) => $q->open())
                    ->selectRaw('COALESCE(SUM(preco), 0)'),
            ])
            ->get()
            ->map(fn($u) => [
                'id'             => $u->id,
                'nome'           => $u->nome,
                'email'          => $u->email,
                'telefone'       => $u->telefone,
                'descricao'      => $u->descricao,
                'estagio_id'     => $u->estagio_id,
                'ultimo_contato' => $u->ultimo_contato ?? $u->updated_at,
                'valor_projetos' => (float) ($u->valor_projetos ?? 0),
            ]);

        // Um estágio arquivado (soft delete) continua vindo enquanto ainda
        // restar lead nele. O quadro distribui os leads comparando estagio_id com
        // o id de cada coluna: sem a coluna, o lead não renderiza em lugar
        // nenhum e some em silêncio. Marcada como `arquivada`, a UI a mostra
        // como somente-saída; esvaziada, ela para de vir e a coluna desaparece.
        $estagiosArquivadosEmUso = $leads->pluck('estagio_id')->filter()->unique();

        $estagios = Estagio::withTrashed()
            ->where('funil_id', $funil->id)
            ->where(fn ($q) => $q->whereNull('deleted_at')
                ->orWhereIn('id', $estagiosArquivadosEmUso))
            ->orderBy('ordem')
            ->orderBy('id')
            ->get()
            ->map(fn ($estagio) => [
                'id'        => $estagio->id,
                'descricao' => $estagio->descricao,
                'ordem'     => $estagio->ordem,
                'tipo'      => $estagio->tipo,
                'cor'       => $estagio->cor,
                'arquivada' => $estagio->trashed(),
            ])
            ->values();

        // A lista de funis acompanha o quadro para alimentar o seletor e o
        // diálogo de "mover para outro funil" sem uma segunda requisição.
        $funis = Funil::ordenados()->get()->map(fn (Funil $f) => [
            'id'         => $f->id,
            'nome'       => $f->nome,
            'is_default' => $f->is_default,
        ])->values();

        return response()->json([
            'funil'    => ['id' => $funil->id, 'nome' => $funil->nome, 'is_default' => $funil->is_default],
            'funis'    => $funis,
            'estagios' => $estagios,
            'leads'    => $leads,
        ]);
    }

    public function patchEstagio(Request $request, $id)
    {
        try {
            $usuario = Usuario::findOrFail($id);

            $validated = $request->validate([
                'estagio_id' => [
                    'required',
                    Rule::exists('estagios', 'id')
                        ->where('tenant_id', app(CurrentTenant::class)->id())
                        // Arrastar um card move o lead dentro do quadro, e o
                        // quadro é um funil. Sem este filtro, um estagio_id de
                        // outro funil deixaria o lead com funil e estágio
                        // discordando — estado que nenhuma tela sabe desenhar.
                        // Trocar de funil é outra ação: moverFunil().
                        ->where('funil_id', $usuario->funil_id),
                ],
                ...RegrasDePerda::campos(),
            ], RegrasDePerda::mensagens());

            // Histórico de estágio e activity log ficam a cargo do UsuarioObserver.
            app(AplicarTransicao::class)($usuario, ['estagio_id' => $validated['estagio_id']], RegrasDePerda::extrair($request));

            return response()->json(['message' => 'Estágio atualizado']);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json(['erros' => $error->errors()], 422);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Lead não encontrado'], 404);
        }
    }

    /**
     * Move o lead para outro funil, no estágio de destino escolhido.
     *
     * A regra de negócio mora em MoverLeadDeFunil; aqui só resolvem-se os
     * models. O histórico e o evento `funil_alterado` saem do UsuarioObserver.
     */
    public function moverFunil(Request $request, MoverLeadDeFunil $mover, $id)
    {
        try {
            $usuario = Usuario::findOrFail($id);

            $tenantId = app(CurrentTenant::class)->id();

            $validated = $request->validate([
                'funil_id' => [
                    'required',
                    Rule::exists('funis', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at'),
                ],
                'estagio_id' => [
                    'required',
                    Rule::exists('estagios', 'id')->where('tenant_id', $tenantId),
                ],
                ...RegrasDePerda::campos(),
            ], RegrasDePerda::mensagens());

            $mover(
                $usuario,
                Funil::findOrFail($validated['funil_id']),
                Estagio::findOrFail($validated['estagio_id']),
                RegrasDePerda::extrair($request),
            );

            return response()->json(['message' => 'Lead movido de funil']);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json(['erros' => $error->errors()], 422);
        }
    }

    public function kanbanSettings(Request $request)
    {
        try {
            auth()->user()->update(['kanban_default_estagio_id' => $request->default_estagio_id]);
            return response()->json(['message' => 'Preferência salva']);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Usuário não encontrado'], 404);
        }
    }

    public function metricas(Request $request)
    {
        $userId = auth()->id();
        $leadIds = Usuario::where('user_id', $userId)->pluck('id');
        // `is_active` foi removida: o que separa lead em pipeline de lead
        // encerrado agora é o tipo do estágio. "Aberto" é o que antes era
        // is_active = true; ganho e perdido, juntos, são o que era false.
        $leadsAtivos     = Usuario::where('user_id', $userId)->whereHas('estagio', fn ($q) => $q->aberto())->count();
        $leadsArquivados = Usuario::where('user_id', $userId)->whereHas('estagio', fn ($q) => $q->fechado())->count();
        $leads30Dias     = Usuario::where('user_id', $userId)->where('created_at', '>=', now()->subDays(30))->count();
        $valorAberto = Projeto::whereIn('usuario_id', $leadIds)->whereHas('status', fn ($q) => $q->open())->sum('preco') ?? 0;
        $valorFechadoMes = Projeto::whereIn('usuario_id', $leadIds)->whereHas('status', fn ($q) => $q->where('is_won', true))->whereMonth('updated_at', now()->month)->whereYear('updated_at', now()->year)->sum('preco') ?? 0;
        $leadsPorEstagio = Usuario::where('user_id', $userId)->select('estagio_id', DB::raw('count(*) as total'))->with('estagio')->groupBy('estagio_id')->get()
            ->map(fn($row) => [
                'id'        => $row->estagio_id,
                'descricao' => $row->estagio->descricao ?? 'Sem estágio',
                'total'     => (int) $row->total,
            ]);
        $totalLeads      = $leadIds->count();
        $leadsComProjeto = $leadIds->isNotEmpty()
            ? Projeto::whereIn('usuario_id', $leadIds)->distinct('usuario_id')->count('usuario_id')
            : 0;
        $taxaConversao   = $totalLeads > 0 ? round($leadsComProjeto / $totalLeads * 100, 1) : 0;

        return response()->json([
            'leads_ativos'           => $leadsAtivos,
            'leads_arquivados'       => $leadsArquivados,
            'leads_30_dias'          => $leads30Dias,
            'valor_projetos_abertos' => (float) $valorAberto,
            'valor_fechado_mes'      => (float) $valorFechadoMes,
            'leads_por_estagio'      => $leadsPorEstagio,
            'taxa_conversao'         => $taxaConversao,
            'total_com_projeto'      => $leadsComProjeto,
            'total_leads'            => $totalLeads,
        ]);
    }
}
