<?php

namespace App\Queries\Tarefas;

use App\Models\Tarefa;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Listagem\Ordenacao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista global de tarefas (página Tarefas e API): tarefas dos leads que o
 * agente enxerga, por escopo de vencimento, paginada e ordenável.
 * Comparações de data por valor (`DATE` com `DATE`), não `whereDate()`,
 * para usar o índice (tenant_id, concluido, data_limite).
 */
class ListagemDeTarefas
{
    public const ESCOPO_PADRAO = 'pendentes';

    /**
     * @param  array{escopo?:string, user_id?:int, busca?:string}  $filtros
     */
    public function query(User $user, array $filtros, ?Ordenacao $ordenacao = null): Builder
    {
        $ordenacao ??= new Ordenacao('data_limite', 'asc', 'tarefas.data_limite');
        $hoje = today()->toDateString();

        $visiveis = Usuario::idsVisiveisPara($user)
            ->when(($filtros['user_id'] ?? null) && $user->can('leads.view-all'), fn ($q) => $q->where('usuarios.user_id', (int) $filtros['user_id']));

        $q = Tarefa::query()
            ->whereIn('tarefas.usuario_id', $visiveis)
            ->with('lead:id,nome,user_id', 'lead.responsavel:id,name')
            ->when($filtros['busca'] ?? null, function (Builder $q, string $busca) {
                $termo = '%'.addcslashes($busca, '%_\\').'%';
                $q->where(fn ($w) => $w->where('tarefas.titulo', 'like', $termo)
                    ->orWhereHas('lead', fn ($l) => $l->where('usuarios.nome', 'like', $termo)));
            });

        match ($filtros['escopo'] ?? self::ESCOPO_PADRAO) {
            'atrasadas' => $q->where('concluido', false)->where('data_limite', '<', $hoje),
            'hoje' => $q->where('concluido', false)->where('data_limite', $hoje),
            'proximos_7' => $q->where('concluido', false)->whereBetween('data_limite', [$hoje, today()->addDays(7)->toDateString()]),
            'pendentes' => $q->where('concluido', false),
            'concluidas' => $q->where('concluido', true),
            default => null,
        };

        return $ordenacao->aplicar($q, 'tarefas.id');
    }

    public function __invoke(User $user, array $filtros, ?Ordenacao $ordenacao, int $porPagina): LengthAwarePaginator
    {
        return $this->query($user, $filtros, $ordenacao)->paginate($porPagina)->withQueryString();
    }
}
