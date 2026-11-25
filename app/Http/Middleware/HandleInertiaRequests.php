<?php

namespace App\Http\Middleware;

use App\Services\Assinaturas\Assinaturas;
use App\Services\Onboarding\PreferenciasDaEmpresa;
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
            'app' => [
                'nome' => config('app.name'),
                // Planos e teste: o cadastro mostra qual plano a pessoa escolheu no site e por quantos dias é grátis.
                'dias_de_teste' => (int) config('comercial.dias_de_teste', 14),
                'planos' => array_map(fn (array $p) => ['chave' => $p['chave'], 'nome' => $p['nome'], 'preco_mensal' => $p['preco_mensal'], 'preco_anual' => $p['preco_anual'], 'limite' => $p['limite'] ?? null, 'destaque' => (bool) ($p['destaque'] ?? false)], config('comercial.planos', [])),
            ],
            // Situação da assinatura (teste, plano, limites, uso) para a faixa do layout e a página de planos.
            'assinatura' => fn () => $this->assinaturaDe($request),
            'auth' => [
                // Lista fechada: o model inteiro levaria junto a empresa (CPF/CNPJ,
                // id do cliente no Asaas), papéis e o que mais for carregado depois.
                'user' => $request->user()?->only(['id', 'name', 'email', 'email_verified_at', 'kanban_default_estagio_id', 'tour_estado', 'tour_versao', 'tour_passo']),
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
            // O que o questionário de primeiro acesso mudou no uso: rótulo do
            // nome do lead, opções de origem e se o passo a passo fala em equipe.
            'empresa' => fn () => ($tenantId = $request->user()?->tenant_id) === null ? null : [
                'rotulo_nome_lead' => PreferenciasDaEmpresa::rotuloDoNome($tenantId),
                'origens' => PreferenciasDaEmpresa::origens($tenantId),
                'equipe_maior_que_um' => PreferenciasDaEmpresa::equipeComMaisDeUm($tenantId),
            ],
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

    /** Resumo da assinatura; o link de pagamento da fatura só vai para quem gerencia a assinatura. */
    private function assinaturaDe(Request $request): ?array
    {
        $user = $request->user();
        if (! $user?->tenant_id) {
            return null;
        }
        $resumo = app(Assinaturas::class)->resumo($user->tenant_id);
        if (is_array($resumo['fatura_pendente'] ?? null) && ! $user->can('configuracoes.manage')) {
            $resumo['fatura_pendente']['link_pagamento'] = null;
        }

        return $resumo;
    }
}
