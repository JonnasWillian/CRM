<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\LeadController as LeadWeb;
use App\Models\Convite;
use App\Models\User;
use App\Services\Equipe\Convites;
use App\Services\Equipe\Membros;
use App\Support\Equipe\RegrasDeAdmin;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Equipe: membros e convites. Rotas sob can:agentes.manage, exceto `opcoes`. */
class EquipeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => self::membros()]);
    }

    /** Membros ativos para selects de responsável (quem vê a empresa inteira). */
    public function opcoes(Request $request): JsonResponse
    {
        return response()->json(LeadWeb::agentes($request->user()));
    }

    public function papel(Request $request, Membros $membros, User $agente): JsonResponse
    {
        $this->authorize('gerenciar', $agente);
        $dados = $request->validate(['papel' => ['required', Rule::in(Convite::PAPEIS)]]);
        $membros->alterarPapel($request->user(), $agente, $dados['papel']);

        return response()->json(['message' => "Papel de “{$agente->name}” alterado."]);
    }

    public function desativar(Request $request, Membros $membros, User $agente): JsonResponse
    {
        $this->authorize('gerenciar', $agente);
        $dados = $request->validate(
            ['transferir_para' => ['nullable', 'integer', RegrasDeAdmin::regraDeMembroAtivo(app(CurrentTenant::class)->id())]],
            ['transferir_para.exists' => 'Escolha um membro ativo da empresa.'],
        );
        $membros->desativar($request->user(), $agente, isset($dados['transferir_para']) ? User::find($dados['transferir_para']) : null);

        return response()->json(['message' => "“{$agente->name}” desativado."]);
    }

    public function reativar(Membros $membros, User $agente): JsonResponse
    {
        $this->authorize('gerenciar', $agente);
        $membros->reativar($agente);

        return response()->json(['message' => "“{$agente->name}” reativado."]);
    }

    public function convidar(Request $request, Convites $convites): JsonResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'papel' => ['required', Rule::in(Convite::PAPEIS)],
        ], ['email.unique' => 'Este e-mail já tem uma conta no sistema.']);

        $convite = $convites->convidar($request->user(), $dados['email'], $dados['papel']);

        return response()->json(['message' => "Convite enviado para {$convite->email}. Vale por ".Convites::VALIDADE_DIAS.' dias.', 'data' => self::convite($convite)], 201);
    }

    public function reenviar(Request $request, Convites $convites, Convite $convite): JsonResponse
    {
        $convites->reenviar($convite, $request->user());

        return response()->json(['message' => "Convite reenviado para {$convite->email}.", 'data' => self::convite($convite)]);
    }

    public function cancelar(Convites $convites, Convite $convite): JsonResponse
    {
        $convites->cancelar($convite);

        return response()->json(['message' => 'Convite cancelado.']);
    }

    /** @return list<array<string, mixed>> */
    public static function membros(): array
    {
        return User::doTenant(app(CurrentTenant::class)->id())
            ->with('roles')
            ->withCount('leads')
            ->orderByDesc('ativo')->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'papel' => $u->roles->first()?->name,
                'ativo' => (bool) $u->ativo,
                'desativado_em' => $u->desativado_em?->toIso8601String(),
                'created_at' => $u->created_at?->toIso8601String(),
                'leads_count' => (int) $u->leads_count,
            ])->all();
    }

    /** Convites não aceitos nem cancelados (pendentes e expirados, para reenviar). */
    public static function convites(): array
    {
        return Convite::query()->whereNull('aceito_em')->whereNull('cancelado_em')
            ->with('convidadoPor:id,name')->latest()->get()
            ->map(fn (Convite $c) => self::convite($c))->all();
    }

    public static function convite(Convite $c): array
    {
        return [
            'id' => $c->id,
            'email' => $c->email,
            'papel' => $c->papel,
            'estado' => Convites::estado($c),
            'enviado_em' => $c->updated_at?->toIso8601String(),
            'expira_em' => $c->expira_em?->toIso8601String(),
            'convidado_por' => $c->convidadoPor?->name,
        ];
    }
}
