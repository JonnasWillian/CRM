<?php

namespace App\Http\Middleware;

use App\Support\LimitesDeTexto;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\PermissionRegistrar;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            // Nome do produto: logo e títulos leem daqui (APP_NAME), nunca de texto cravado.
            'app' => ['nome' => config('app.name')],
            // Situação da assinatura (teste, plano, limites, uso) para a faixa do layout e a página de planos.
            'assinatura' => fn () => $request->user()?->tenant_id ? app(\App\Services\Assinaturas\Assinaturas::class)->resumo($request->user()->tenant_id) : null,
            'auth' => [
                'user' => $request->user(),
                // Closures, não arrays prontos: Inertia::share() é chamado por
                // este middleware ANTES de $next($request), e o middleware de
                // rota `tenant` — que define o team do spatie via
                // setPermissionsTeamId() — só roda depois. Avaliar can() aqui e
                // agora consultaria tenant_id = null e devolveria false para
                // todo mundo. As closures são resolvidas na montagem da
                // resposta, quando o team já está definido.
                //
                // O mapa é COMPLETO (toda permission conhecida => bool): o
                // front decide menu e botões a partir dele, e uma permission
                // ausente seria lida como "não tem" — indistinguível de
                // "esqueceram de compartilhar".
                'permissions' => fn () => $this->permissoesDe($request),
                'roles' => fn () => $request->user()?->getRoleNames()->values()->all() ?? [],
            ],
            // Mensagens de uma requisição para a próxima (redirects do Inertia).
            // O layout as converte em toast.
            'flash' => fn () => [
                'sucesso' => $request->session()->get('sucesso'),
                'erro' => $request->session()->get('erro'),
            ],
            // Fonte única de limites de texto (validação e colunas), para o
            // front aplicar o mesmo `maxlength` sem duplicar o número.
            'limites' => LimitesDeTexto::paraOFront(),
        ];
    }

    /** @return array<string, bool> */
    private function permissoesDe(Request $request): array
    {
        $user = $request->user();

        return app(PermissionRegistrar::class)
            ->getPermissions()
            ->pluck('name')
            ->mapWithKeys(fn (string $nome) => [$nome => $user !== null && $user->can($nome)])
            ->all();
    }
}
