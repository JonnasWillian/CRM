<?php

namespace App\Queries\Lixeira;

use App\Models\Activity;
use App\Models\Projeto;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Leads\BuscaDeLeads;
use App\Support\Listagem\Ordenacao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A Lixeira: leads (onlyTrashed, visibleTo) ou projetos excluídos dos leads
 * que o agente enxerga. "Excluído por" vem do activity log numa consulta
 * por página. Projeto cujo lead também está na lixeira vem marcado: só
 * volta depois do lead.
 */
class ListagemDaLixeira
{
    public function __invoke(User $user, array $filtros, Ordenacao $ordenacao, int $porPagina): LengthAwarePaginator
    {
        $projetos = ($filtros['tipo'] ?? 'leads') === 'projetos';
        $q = $projetos ? $this->projetos($user, $filtros) : $this->leads($user, $filtros);
        $tabela = $projetos ? 'projetos' : 'usuarios';

        $pagina = (new Ordenacao($ordenacao->por, $ordenacao->dir, "{$tabela}.{$ordenacao->coluna}"))
            ->aplicar($q, "{$tabela}.id")
            ->paginate($porPagina)->withQueryString();

        $porQuem = $this->excluidoPor($pagina->getCollection(), $projetos ? 'projeto_removido' : 'lead_removido', $projetos ? 'projeto' : 'lead');
        $pagina->setCollection($pagina->getCollection()->map(fn ($m) => $projetos ? $this->projeto($m, $porQuem) : $this->lead($m, $porQuem)));

        return $pagina;
    }

    public static function leadsVisiveis(User $user): Builder
    {
        return Usuario::onlyTrashed()->visibleTo($user);
    }

    public static function projetosVisiveis(User $user): Builder
    {
        return Projeto::onlyTrashed()->whereIn('projetos.usuario_id', Usuario::withTrashed()->visibleTo($user)->select('usuarios.id'));
    }

    private function leads(User $user, array $filtros): Builder
    {
        return self::leadsVisiveis($user)
            ->select('usuarios.*')
            ->with(['estagio', 'responsavel:id,name'])
            ->when($filtros['busca'] ?? null, fn (Builder $q, string $b) => BuscaDeLeads::aplicar($q, $b))
            ->when(($filtros['user_id'] ?? null) && $user->can('leads.view-all'), fn (Builder $q) => $q->where('usuarios.user_id', (int) $filtros['user_id']));
    }

    private function projetos(User $user, array $filtros): Builder
    {
        return self::projetosVisiveis($user)
            ->select('projetos.*')
            ->with(['usuario' => fn ($q) => $q->withTrashed()->with('responsavel:id,name')])
            ->when($filtros['busca'] ?? null, fn (Builder $q, string $b) => $q->where('projetos.nome', 'like', '%'.addcslashes($b, '%_\\').'%'))
            ->when(($filtros['user_id'] ?? null) && $user->can('leads.view-all'), fn (Builder $q) => $q->whereIn('projetos.usuario_id', Usuario::withTrashed()->where('user_id', (int) $filtros['user_id'])->select('id')));
    }

    /** @return array<int, string> subject_id => nome de quem excluiu (a exclusão mais recente) */
    private function excluidoPor(Collection $itens, string $evento, string $tipo): array
    {
        if ($itens->isEmpty()) {
            return [];
        }

        $linhas = Activity::query()
            ->where('subject_type', $tipo)
            ->where('event', $evento)
            ->whereIn('subject_id', $itens->pluck('id'))
            ->orderBy('id')
            ->get(['subject_id', 'causer_id'])
            ->keyBy('subject_id');

        $nomes = User::whereIn('id', $linhas->pluck('causer_id')->filter()->unique())->pluck('name', 'id');

        return $linhas->map(fn ($l) => $nomes[$l->causer_id] ?? null)->filter()->all();
    }

    private function lead(Usuario $u, array $porQuem): array
    {
        return [
            'id' => $u->id,
            'nome' => $u->nome,
            'email' => $u->email,
            'estagio' => $u->estagio ? ['descricao' => $u->estagio->descricao, 'cor' => $u->estagio->cor, 'tipo' => $u->estagio->tipo, 'arquivado' => $u->estagio->trashed()] : null,
            'responsavel' => $u->responsavel?->only(['id', 'name']),
            'deleted_at' => $u->deleted_at?->toIso8601String(),
            'excluido_por' => $porQuem[$u->id] ?? null,
        ];
    }

    private function projeto(Projeto $p, array $porQuem): array
    {
        return [
            'id' => $p->id,
            'nome' => $p->nome,
            'preco' => $p->preco,
            'lead' => $p->usuario ? ['id' => $p->usuario->id, 'nome' => $p->usuario->nome] : null,
            'lead_na_lixeira' => (bool) $p->usuario?->trashed(),
            'responsavel' => $p->usuario?->responsavel?->only(['id', 'name']),
            'deleted_at' => $p->deleted_at?->toIso8601String(),
            'excluido_por' => $porQuem[$p->id] ?? null,
        ];
    }
}
