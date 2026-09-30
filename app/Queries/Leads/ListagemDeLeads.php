<?php

namespace App\Queries\Leads;

use App\Models\Projeto;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * A lista de leads do Dashboard: visibilidade, filtros, ordem e paginação,
 * todos no banco.
 *
 * Os filtros são os mesmos que Dashboard.vue aplicava no navegador sobre a
 * carteira inteira (busca, estágios, período, situação), com a mesma
 * semântica — só mudou onde rodam.
 */
class ListagemDeLeads
{
    public const POR_PAGINA = 25;
    public const MAX_POR_PAGINA = 100;

    /**
     * @param  array{busca?:string, estagios?:list<int>, de?:string, ate?:string, status?:string, per_page?:int}  $filtros
     */
    public function __invoke(User $user, array $filtros): LengthAwarePaginator
    {
        $porPagina = min(max((int) ($filtros['per_page'] ?? self::POR_PAGINA), 1), self::MAX_POR_PAGINA);

        return Usuario::visibleTo($user)
            ->with('estagio')
            ->addSelect([
                'usuarios.*',
                'tem_projeto' => Projeto::whereColumn('usuario_id', 'usuarios.id')->selectRaw('COUNT(*) > 0'),
                'tem_projeto_aberto' => Projeto::whereColumn('usuario_id', 'usuarios.id')
                    ->whereHas('status', fn ($q) => $q->open())
                    ->selectRaw('COUNT(*) > 0'),
            ])
            ->when($filtros['busca'] ?? null, fn (Builder $q, string $busca) => $this->buscar($q, $busca))
            ->when($filtros['estagios'] ?? null, fn (Builder $q, array $ids) => $q->whereIn('usuarios.estagio_id', $ids))
            ->when($filtros['de'] ?? null, fn (Builder $q, string $de) => $q->where('usuarios.created_at', '>=', $this->limite($de, 'inicio')))
            ->when($filtros['ate'] ?? null, fn (Builder $q, string $ate) => $q->where('usuarios.created_at', '<=', $this->limite($ate, 'fim')))
            ->when($filtros['status'] ?? null, fn (Builder $q, string $status) => $this->situacao($q, $status))
            ->orderByDesc('usuarios.created_at')
            ->orderByDesc('usuarios.id')
            ->paginate($porPagina)
            ->withQueryString();
    }

    /**
     * O Dashboard manda o instante ISO completo do início/fim do dia no fuso
     * de quem filtra (00:00 em Brasília = 03:00Z); esse vale como veio —
     * arredondar para o dia em UTC deslocava o período em um dia. Valor só
     * data (YYYY-MM-DD, de outros clientes) continua cobrindo o dia inteiro.
     * O fuso da aplicação é o do banco, então o instante é convertido para ele.
     */
    private function limite(string $valor, string $lado): Carbon
    {
        $instante = Carbon::parse($valor);

        if (str_contains($valor, 'T')) {
            return $instante->setTimezone(config('app.timezone'));
        }

        return $lado === 'inicio' ? $instante->startOfDay() : $instante->endOfDay();
    }

    private function buscar(Builder $q, string $busca): void
    {
        // Review Focus 4: % e _ do usuário são texto, não curinga.
        $termo = '%'.addcslashes($busca, '%_\\').'%';
        $digitos = preg_replace('/\D/', '', $busca);

        $q->where(function (Builder $w) use ($termo, $digitos) {
            $w->where('usuarios.nome', 'like', $termo)
                ->orWhere('usuarios.email', 'like', $termo)
                ->orWhere('usuarios.descricao', 'like', $termo);

            // Telefone é E.164 no banco: "(11) 98888" vira "1198888".
            if (strlen($digitos) >= 3) {
                $w->orWhere('usuarios.telefone', 'like', '%'.$digitos.'%');
            }
        });
    }

    private function situacao(Builder $q, string $status): void
    {
        match ($status) {
            'arquivado' => $q->whereHas('estagio', fn ($e) => $e->fechado()),
            'aberto' => $q->whereHas('projetos', fn ($p) => $p->whereHas('status', fn ($s) => $s->open())),
            'sem_projeto' => $q->whereDoesntHave('projetos'),
            default => null,
        };
    }
}
