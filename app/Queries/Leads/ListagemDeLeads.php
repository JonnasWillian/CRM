<?php

namespace App\Queries\Leads;

use App\Models\Estagio;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Listagem\Ordenacao;
use App\Support\Listagem\Paginacao;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * A grade de leads: visibilidade, filtros, ordem e paginação, todos no banco.
 *
 * `query()` devolve o Builder para quem precisa da mesma seleção sem
 * paginar (exportação CSV, ações em massa por filtro); `__invoke()` pagina.
 */
class ListagemDeLeads
{
    /**
     * @param  array{busca?:string, estagios?:list<int>, de?:string, ate?:string, situacao?:string, funil_id?:int, user_id?:int, estagnados?:bool, previsao_de?:string, previsao_ate?:string}  $filtros
     */
    public function query(User $user, array $filtros, ?Ordenacao $ordenacao = null): Builder
    {
        $ordenacao ??= new Ordenacao('created_at', 'desc', 'usuarios.created_at');

        $q = Usuario::visibleTo($user)
            ->select('usuarios.*')
            ->with(['estagio', 'responsavel:id,name'])
            // EXISTS para na primeira linha; COUNT(*) > 0 varria todas.
            ->withExists([
                'projetos as tem_projeto',
                'projetos as tem_projeto_aberto' => fn ($p) => $p->whereHas('status', fn ($s) => $s->open()),
            ])
            ->when($filtros['busca'] ?? null, fn (Builder $q, string $busca) => $this->buscar($q, $busca))
            ->when($filtros['estagios'] ?? null, fn (Builder $q, array $ids) => $q->whereIn('usuarios.estagio_id', $ids))
            ->when($filtros['de'] ?? null, fn (Builder $q, string $de) => $q->where('usuarios.created_at', '>=', $this->limite($de, 'inicio')))
            ->when($filtros['ate'] ?? null, fn (Builder $q, string $ate) => $q->where('usuarios.created_at', '<=', $this->limite($ate, 'fim')))
            ->when($filtros['situacao'] ?? null, fn (Builder $q, string $situacao) => $this->situacao($q, $situacao))
            ->when($filtros['funil_id'] ?? null, fn (Builder $q, int $funilId) => $q->where('usuarios.funil_id', $funilId))
            // Vendedor já vê só a própria carteira: o parâmetro é ignorado para ele.
            ->when(($filtros['user_id'] ?? null) && $user->can('leads.view-all'), fn (Builder $q) => $q->where('usuarios.user_id', (int) $filtros['user_id']))
            ->when(filter_var($filtros['estagnados'] ?? false, FILTER_VALIDATE_BOOL), fn (Builder $q) => $q
                ->where('usuarios.ultimo_contato_em', '<', now()->subDays((int) config('leads.dias_estagnado', 14))))
            ->when($filtros['previsao_de'] ?? null, fn (Builder $q, string $de) => $q->whereDate('usuarios.previsao_fechamento', '>=', $de))
            ->when($filtros['previsao_ate'] ?? null, fn (Builder $q, string $ate) => $q->whereDate('usuarios.previsao_fechamento', '<=', $ate));

        // Joins só quando a ordem pede a coluna da outra tabela.
        if ($ordenacao->por === 'estagio') {
            $q->leftJoin('estagios', 'estagios.id', '=', 'usuarios.estagio_id');
        } elseif ($ordenacao->por === 'responsavel') {
            $q->leftJoin('users', 'users.id', '=', 'usuarios.user_id');
        }

        return $ordenacao->aplicar($q, 'usuarios.id');
    }

    public function __invoke(User $user, array $filtros, ?Ordenacao $ordenacao = null, ?int $porPagina = null): LengthAwarePaginator
    {
        return $this->query($user, $filtros, $ordenacao)
            ->paginate($porPagina ?? Paginacao::clamp($filtros['per_page'] ?? null))
            ->withQueryString();
    }

    /**
     * O Dashboard manda o instante ISO completo do início/fim do dia no fuso
     * de quem filtra (00:00 em Brasília = 03:00Z); esse vale como veio —
     * arredondar para o dia em UTC deslocava o período em um dia. Valor só
     * data (YYYY-MM-DD, de outros clientes) continua cobrindo o dia inteiro.
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
        // % e _ do usuário são texto, não curinga.
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

    private function situacao(Builder $q, string $situacao): void
    {
        match ($situacao) {
            'ativos' => $q->whereHas('estagio', fn ($e) => $e->aberto()),
            'ganhos' => $q->whereHas('estagio', fn ($e) => $e->where('tipo', Estagio::TIPO_GANHO)),
            'perdidos' => $q->whereHas('estagio', fn ($e) => $e->where('tipo', Estagio::TIPO_PERDIDO)),
            'fechados' => $q->whereHas('estagio', fn ($e) => $e->fechado()),
            'com_projeto_aberto' => $q->whereHas('projetos', fn ($p) => $p->whereHas('status', fn ($s) => $s->open())),
            'sem_projeto' => $q->whereDoesntHave('projetos'),
            default => null,
        };
    }
}
