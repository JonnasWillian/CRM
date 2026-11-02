<?php

namespace App\Services\Onboarding;

use App\Http\Requests\MetricasRequest;
use App\Models\User;

/**
 * Em que visão o Dashboard abre para quem vê a equipe toda: a preferência
 * `dashboard_visao` (do cargo respondido no questionário) vale quando a URL
 * não escolhe nada. Escolher "Toda a equipe" (`visao=equipe`) ou a si mesmo
 * no filtro passa a ser a preferência; escolher outro vendedor, não.
 * Só o Dashboard usa isto; Relatórios, Leads e Pipeline seguem a URL.
 */
class VisaoDoDashboard
{
    public static function agente(MetricasRequest $request): ?User
    {
        $eu = $request->user();
        if (! $eu->can('leads.view-all')) {
            return $request->agente();
        }

        if ($request->filled('user_id')) {
            $agente = $request->agente();
            if ($agente?->id === $eu->id) {
                self::lembrar($eu, 'meus');
            }

            return $agente;
        }

        if ($request->query('visao') === 'equipe') {
            self::lembrar($eu, 'equipe');

            return null;
        }

        return $eu->dashboard_visao === 'meus' ? $eu : null;
    }

    private static function lembrar(User $eu, string $visao): void
    {
        if ($eu->dashboard_visao !== $visao) {
            $eu->forceFill(['dashboard_visao' => $visao])->save();
        }
    }
}
