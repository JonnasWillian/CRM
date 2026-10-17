<?php

namespace App\Queries\Kanban;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Projeto;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Leads\BuscaDeLeads;
use App\Support\Listagem\Ordenacao;
use App\Support\Listagem\Paginacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * O quadro agrupado no banco: uma consulta de agregados por coluna (total,
 * Σ valor estimado, Σ ponderado, sem probabilidade), uma de Σ projetos
 * abertos por coluna, uma de estágios e N consultas `LIMIT` — uma por
 * coluna — para os primeiros cards. "Carregar mais" pagina uma coluna só.
 * O custo não cresce com o número de leads do funil.
 */
class QuadroDoKanban
{
    /**
     * @param  array{busca:?string, user_id:?int, estagnados:bool, por_coluna:int}  $filtros
     */
    public function __invoke(User $user, Funil $funil, array $filtros): array
    {
        $base = fn (): Builder => $this->base($user, $funil, $filtros);

        $agregados = $base()
            ->selectRaw('usuarios.estagio_id,
                COUNT(*) as total,
                COALESCE(SUM(valor_estimado), 0) as valor_estimado,
                COALESCE(SUM(CASE WHEN probabilidade IS NOT NULL THEN valor_estimado * probabilidade / 100 END), 0) as valor_ponderado,
                SUM(CASE WHEN valor_estimado IS NOT NULL AND probabilidade IS NULL THEN 1 ELSE 0 END) as sem_probabilidade')
            ->groupBy('usuarios.estagio_id')
            ->get()
            ->keyBy('estagio_id');

        $projetos = Projeto::query()
            ->join('usuarios', 'usuarios.id', '=', 'projetos.usuario_id')
            ->whereIn('projetos.usuario_id', $base()->select('usuarios.id'))
            ->whereHas('status', fn ($s) => $s->open())
            ->groupBy('usuarios.estagio_id')
            ->selectRaw('usuarios.estagio_id, COALESCE(SUM(projetos.preco), 0) as valor')
            ->pluck('valor', 'estagio_id');

        // Um estágio arquivado continua vindo enquanto ainda restar lead nele
        // (somente-saída); esvaziado, a coluna some.
        $emUso = $agregados->keys()->filter()->all();
        $estagios = Estagio::withTrashed()
            ->where('funil_id', $funil->id)
            ->where(fn ($q) => $q->whereNull('deleted_at')->orWhereIn('id', $emUso))
            ->orderBy('ordem')->orderBy('id')
            ->get();

        $colunas = $estagios->map(function (Estagio $estagio) use ($agregados, $projetos, $base, $filtros) {
            $a = $agregados->get($estagio->id);
            $total = (int) ($a->total ?? 0);
            $leads = $total > 0
                ? self::cards($this->cardsQuery($base()->where('usuarios.estagio_id', $estagio->id))->limit($filtros['por_coluna'])->get(), $estagio->tipo)
                : [];

            return [
                'id' => $estagio->id,
                'descricao' => $estagio->descricao,
                'ordem' => $estagio->ordem,
                'tipo' => $estagio->tipo,
                'cor' => $estagio->cor,
                'arquivada' => $estagio->trashed(),
                'probabilidade_padrao' => $estagio->probabilidade_padrao,
                'total' => $total,
                'valor_estimado' => (float) ($a->valor_estimado ?? 0),
                'valor_ponderado' => (float) ($a->valor_ponderado ?? 0),
                'sem_probabilidade' => (int) ($a->sem_probabilidade ?? 0),
                'valor_projetos' => (float) ($projetos[$estagio->id] ?? 0),
                'leads' => $leads,
                'proxima_pagina' => $total > $filtros['por_coluna'] ? 2 : null,
            ];
        })->values();

        $abertas = $colunas->where('tipo', Estagio::TIPO_ABERTO);

        return [
            'funil' => ['id' => $funil->id, 'nome' => $funil->nome, 'is_default' => $funil->is_default],
            'filtros' => ['busca' => $filtros['busca'], 'user_id' => $filtros['user_id'], 'estagnados' => $filtros['estagnados'], 'por_coluna' => $filtros['por_coluna']],
            'totais' => [
                'leads' => (int) $colunas->sum('total'),
                'valor_estimado_aberto' => (float) $abertas->sum('valor_estimado'),
                'valor_ponderado_aberto' => (float) $abertas->sum('valor_ponderado'),
                'valor_projetos_aberto' => (float) $abertas->sum('valor_projetos'),
            ],
            'estagios' => $colunas->all(),
        ];
    }

    /** Uma página de uma coluna ("carregar mais"). */
    public function coluna(User $user, Estagio $estagio, array $filtros, ?int $pagina): array
    {
        $funil = Funil::withTrashed()->findOrFail($estagio->funil_id);
        $q = $this->cardsQuery($this->base($user, $funil, $filtros)->where('usuarios.estagio_id', $estagio->id));
        $paginador = $q->paginate($filtros['por_coluna'], ['*'], 'page', $pagina)->withQueryString();
        $paginador->setCollection(collect(self::cards($paginador->getCollection(), $estagio->tipo)));

        return Paginacao::resposta($paginador, new Ordenacao('created_at', 'desc', 'usuarios.created_at'), ['estagio_id' => $estagio->id]);
    }

    private function base(User $user, Funil $funil, array $filtros): Builder
    {
        return Usuario::visibleTo($user)
            ->where('usuarios.funil_id', $funil->id)
            ->when($filtros['busca'], fn (Builder $q, string $busca) => BuscaDeLeads::aplicar($q, $busca))
            ->when($filtros['user_id'], fn (Builder $q, int $id) => $q->where('usuarios.user_id', $id))
            ->when($filtros['estagnados'], fn (Builder $q) => $q->where('usuarios.ultimo_contato_em', '<', self::limiteEstagnado()));
    }

    private function cardsQuery(Builder $q): Builder
    {
        return $q->select('usuarios.*')
            ->with('responsavel:id,name')
            ->withCount(['projetos as projetos_abertos_count' => fn ($p) => $p->whereHas('status', fn ($s) => $s->open())])
            ->addSelect(['valor_projetos' => Projeto::whereColumn('usuario_id', 'usuarios.id')
                ->whereHas('status', fn ($s) => $s->open())
                ->selectRaw('COALESCE(SUM(preco), 0)')])
            ->orderByDesc('usuarios.created_at')->orderByDesc('usuarios.id');
    }

    private static function limiteEstagnado(): \Illuminate\Support\Carbon
    {
        return now()->subDays((int) config('leads.dias_estagnado', 14));
    }

    /**
     * O tipo da coluna vem de fora: ler `$u->estagio` aqui seria uma consulta
     * por card. Só lead em coluna aberta pode estar "parado".
     *
     * @return list<array<string, mixed>>
     */
    public static function cards(Collection $leads, string $tipoDaColuna): array
    {
        $limite = self::limiteEstagnado();
        $aberta = $tipoDaColuna === Estagio::TIPO_ABERTO;

        return $leads->map(fn (Usuario $u) => [
            'id' => $u->id,
            'nome' => $u->nome,
            'email' => $u->email,
            'telefone' => $u->telefone,
            'estagio_id' => $u->estagio_id,
            'funil_id' => $u->funil_id,
            'responsavel' => $u->responsavel ? ['id' => $u->responsavel->id, 'name' => $u->responsavel->name] : null,
            'valor_estimado' => $u->valor_estimado === null ? null : (float) $u->valor_estimado,
            'probabilidade' => $u->probabilidade,
            'previsao_fechamento' => $u->previsao_fechamento?->toDateString(),
            'ultimo_contato' => $u->ultimo_contato_em?->toIso8601String(),
            'estagnado' => $aberta && $u->ultimo_contato_em !== null && $u->ultimo_contato_em->lt($limite),
            'valor_projetos' => (float) ($u->valor_projetos ?? 0),
            'projetos_abertos_count' => (int) ($u->projetos_abertos_count ?? 0),
            'created_at' => $u->created_at?->toIso8601String(),
        ])->all();
    }
}
