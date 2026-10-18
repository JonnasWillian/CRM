<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\LeadController as LeadApi;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListagemDeLeadsRequest;
use App\Http\Requests\UsuarioRequest;
use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Statu;
use App\Models\User;
use App\Models\Usuario;
use App\Queries\Leads\ListagemDeLeads;
use App\Services\Leads\CadastrarLead;
use App\Support\Listagem\Paginacao;
use App\Support\Perdas\RegrasDePerda;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Páginas Inertia de leads: grade, cadastro pelo modal e perfil. */
class LeadController extends Controller
{
    /**
     * A grade. Os dados vêm por props (sem rajada de axios no mount) e cada
     * mudança de filtro/ordem/página é um `router.get` com `only: ['leads']`.
     */
    public function index(ListagemDeLeadsRequest $request, ListagemDeLeads $listagem): Response
    {
        $ordenacao = $request->ordenacao();
        $filtros = $request->filtrosValidados();

        return Inertia::render('Leads/Index', [
            'leads' => Paginacao::resposta($listagem($request->user(), $filtros, $ordenacao, $request->porPagina()), $ordenacao, $filtros),
            'estagios' => Estagio::query()->orderBy('funil_id')->orderBy('ordem')->orderBy('id')
                ->get(['id', 'funil_id', 'descricao', 'cor', 'tipo', 'ordem', 'probabilidade_padrao']),
            'funis' => Funil::ordenados()->get(['id', 'nome', 'is_default']),
            'agentes' => self::agentes($request->user()),
        ]);
    }

    /** Cadastro pelo modal da grade (useForm): erros voltam na sessão, sucesso vira flash. */
    public function store(UsuarioRequest $request, CadastrarLead $cadastrar): RedirectResponse
    {
        $lead = $cadastrar($request->validated(), RegrasDePerda::extrair($request), $request->user());

        if ($request->boolean('abrir')) {
            return redirect()->route('leads.show', $lead)->with('sucesso', 'Lead cadastrado.');
        }

        return back()->with('sucesso', "Lead “{$lead->nome}” cadastrado.");
    }

    /**
     * Perfil do lead. A autorização está no middleware da rota
     * (`->can('view', 'usuario')`), que roda depois do binding.
     */
    public function show(\Illuminate\Http\Request $request, Usuario $usuario): Response
    {
        return Inertia::render('Usuario/Perfil', [
            'lead' => LeadApi::comResumo($usuario),
            'funis' => Funil::ordenados()->with(['estagios' => fn ($q) => $q->orderBy('ordem')->orderBy('id')])->get(['id', 'nome', 'is_default']),
            'status' => Statu::orderBy('ordem')->orderBy('id')->get(['id', 'descricao', 'is_won', 'is_lost']),
            'agentes' => self::agentes($request->user()),
        ]);
    }

    /**
     * Os membros ativos da empresa, para os filtros/selects de responsável.
     * Só para quem tem leads.view-all; o vendedor recebe lista vazia.
     *
     * @return list<array{id:int, name:string}>
     */
    public static function agentes(User $user): array
    {
        if (! $user->can('leads.view-all')) {
            return [];
        }

        return User::doTenant(app(CurrentTenant::class)->id())->ativos()->orderBy('name')->get(['id', 'name'])->all();
    }
}
