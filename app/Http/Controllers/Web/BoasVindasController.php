<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuestionarioRequest;
use App\Models\PerfilDeEmpresa;
use App\Services\Assinaturas\AvisosDeAssinatura;
use App\Services\Onboarding\PersonalizarEmpresa;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Questionário de primeiro acesso (só para a empresa com perfil pendente,
 * criado no cadastro). Respondido ou pulado, volta ao Dashboard, onde as
 * boas-vindas e o passo a passo começam.
 */
class BoasVindasController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if (! PerfilDeEmpresa::pendente($request->user()->tenant_id)) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('BoasVindas/Questionario', [
            'opcoes' => self::opcoes(),
            'nome' => Str::before(trim($request->user()->name).' ', ' '),
        ]);
    }

    public function store(QuestionarioRequest $request, PersonalizarEmpresa $personalizar, AvisosDeAssinatura $avisos, CurrentTenant $tenant): RedirectResponse
    {
        // Já respondido, pulado ou empresa anterior ao questionário: nada a fazer.
        if (! PerfilDeEmpresa::pendente($request->user()->tenant_id)) {
            return redirect()->route('dashboard');
        }
        $perfil = $personalizar->aplicar($tenant->get(), $request->user(), $request->validated());
        if ($perfil?->wasChanged('respondido_em')) {
            $avisos->perfilRespondido($perfil);
        }
        $request->session()->put('perfil_resolvido', true);

        return redirect()->route('dashboard');
    }

    public function pular(Request $request, PersonalizarEmpresa $personalizar, CurrentTenant $tenant): RedirectResponse
    {
        $this->authorize('configuracoes.manage');
        if (PerfilDeEmpresa::pendente($request->user()->tenant_id)) {
            $personalizar->pular($tenant->get(), $request->user());
        }
        $request->session()->put('perfil_resolvido', true);

        return redirect()->route('dashboard');
    }

    /** As opções do config como listas {valor, rotulo}, na ordem do config. */
    private static function opcoes(): array
    {
        $lista = fn (array $grupo) => collect($grupo)
            ->map(fn ($v, $k) => ['valor' => (string) $k, 'rotulo' => is_array($v) ? $v['rotulo'] : $v])
            ->values()->all();

        return collect(['experiencias', 'cargos', 'ramos', 'tamanhos', 'para_quem', 'canais', 'origens', 'conheceu'])
            ->mapWithKeys(fn ($g) => [$g => $lista(config("onboarding.{$g}"))])
            ->all();
    }
}
