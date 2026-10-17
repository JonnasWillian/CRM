<?php

namespace App\Http\Controllers;

use App\Models\Anotacao;
use App\Models\arquivo as ArquivoModel;
use App\Models\Estagio;
use App\Models\EstagioHistorico;
use App\Models\Funil;
use App\Models\Projeto;
use App\Models\ProjetoAnexo;
use App\Models\ProjetoAnotacao;
use App\Models\Tarefa;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * O que sobrou do controller original de leads: kanban (sai na Fase 3c),
 * estágios (lookup) e o timeline deprecado (só o teste de paridade da Fase 5).
 */
class Userarios extends Controller
{
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
     * @deprecated Substituído por LeadAtividadeController::index (activity
     *             log paginado). Mantido só para o teste de paridade; remover
     *             depois que o endpoint novo rodar em produção (spec
     *             2026-09-20-activity-log-design, fase 4).
     */
    public function timeline(Usuario $usuario)
    {
        $this->authorize('view', $usuario);

        $id = $usuario->id;

        $events = [];

        $events[] = [
            'tipo' => 'lead_criado',
            'descricao' => 'Lead cadastrado no sistema',
            'data' => $usuario->created_at,
        ];

        foreach (Anotacao::where('usuario_id', $id)->get() as $a) {
            $events[] = [
                'tipo' => 'anotacao',
                'descricao' => $a->descricao,
                'data' => $a->created_at,
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
                    'tipo' => 'projeto_anotacao',
                    'descricao' => $pa->descricao,
                    'projeto_nome' => $p->nome,
                    'data' => $pa->created_at,
                ];
            }

            foreach (ProjetoAnexo::where('projeto_id', $p->id)->get() as $pf) {
                $events[] = [
                    'tipo' => 'projeto_anexo',
                    'nome' => $pf->nome ?: 'Arquivo sem nome',
                    'projeto_nome' => $p->nome,
                    'data' => $pf->created_at,
                ];
            }
        }

        foreach (EstagioHistorico::with(['estagioAnterior', 'estagioNovo'])->where('usuario_id', $id)->get() as $h) {
            $events[] = [
                'tipo' => 'status_alterado',
                'estagio_anterior' => $h->estagioAnterior->descricao ?? '—',
                'estagio_novo' => $h->estagioNovo->descricao ?? '—',
                'data' => $h->created_at,
            ];
        }

        foreach (Tarefa::where('usuario_id', $id)->get() as $t) {
            $events[] = [
                'tipo' => 'tarefa_criada',
                'titulo' => $t->titulo,
                'data' => $t->created_at,
            ];
            if ($t->concluido && $t->concluido_em) {
                $events[] = [
                    'tipo' => 'tarefa_concluida',
                    'titulo' => $t->titulo,
                    'data' => $t->concluido_em,
                ];
            }
        }

        usort($events, fn ($a, $b) => $b['data'] <=> $a['data']);

        return response()->json($events);
    }

    public function kanban(Request $request)
    {
        // O quadro mostra um funil por vez. Sem funil_id, o padrão do tenant.
        // Com um funil_id que não existe neste tenant, 404 — devolver o padrão
        // calado faria o usuário ver um quadro que não é o que ele pediu.
        $funil = $request->filled('funil_id')
            ? Funil::findOrFail($request->integer('funil_id'))
            : Funil::padrao();

        if ($funil === null) {
            return response()->json(['funil' => null, 'funis' => [], 'estagios' => [], 'leads' => []]);
        }

        $leads = Usuario::visibleTo(auth()->user())
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
            ->map(fn ($u) => [
                'id' => $u->id,
                'nome' => $u->nome,
                'email' => $u->email,
                'telefone' => $u->telefone,
                'descricao' => $u->descricao,
                'estagio_id' => $u->estagio_id,
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
                'id' => $estagio->id,
                'descricao' => $estagio->descricao,
                'ordem' => $estagio->ordem,
                'tipo' => $estagio->tipo,
                'cor' => $estagio->cor,
                'arquivada' => $estagio->trashed(),
            ])
            ->values();

        // A lista de funis acompanha o quadro para alimentar o seletor e o
        // diálogo de "mover para outro funil" sem uma segunda requisição.
        $funis = Funil::ordenados()->get()->map(fn (Funil $f) => [
            'id' => $f->id,
            'nome' => $f->nome,
            'is_default' => $f->is_default,
        ])->values();

        return response()->json([
            'funil' => ['id' => $funil->id, 'nome' => $funil->nome, 'is_default' => $funil->is_default],
            'funis' => $funis,
            'estagios' => $estagios,
            'leads' => $leads,
        ]);
    }

    public function kanbanSettings(Request $request)
    {
        // Sem try/catch: o catch(\Exception) anterior transformava qualquer
        // falha — inclusive a FK recusando um id — em 404 "Usuário não
        // encontrado", o que também servia de oráculo de ids entre tenants.
        $validated = $request->validate([
            'default_estagio_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('estagios', 'id')
                    ->where('tenant_id', app(CurrentTenant::class)->id())
                    ->whereNull('deleted_at'),
            ],
        ]);

        auth()->user()->update(['kanban_default_estagio_id' => $validated['default_estagio_id']]);

        return response()->json(['message' => 'Preferência salva']);
    }
}
