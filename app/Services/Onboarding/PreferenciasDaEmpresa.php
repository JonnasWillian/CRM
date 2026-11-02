<?php

namespace App\Services\Onboarding;

use App\Models\Convite;
use App\Models\PerfilDeEmpresa;
use App\Models\User;

/**
 * O que as respostas do questionário mudam no uso do dia a dia: rótulo do
 * nome do lead, opções de origem, usuários sugeridos na Assinatura, se o
 * passo a passo fala em equipe. Sem questionário respondido, os padrões de
 * antes dele.
 */
class PreferenciasDaEmpresa
{
    /** @return array<string, mixed>|null respostas, só se o questionário foi respondido */
    public static function respostas(int $tenantId): ?array
    {
        $perfil = PerfilDeEmpresa::daEmpresa($tenantId);

        return $perfil?->respondido_em !== null ? $perfil->respostas : null;
    }

    /** @return list<string> rótulos das origens escolhidas e "Outro"; sem resposta, todas */
    public static function origens(int $tenantId): array
    {
        $todas = config('onboarding.origens');
        $escolhidas = self::respostas($tenantId)['origens'] ?? null;
        if (! $escolhidas) {
            return array_values($todas);
        }

        $rotulos = array_values(array_filter(array_map(fn ($k) => $k === 'outro' ? null : ($todas[$k] ?? null), $escolhidas)));

        return [...$rotulos, $todas['outro']];
    }

    public static function rotuloDoNome(int $tenantId): string
    {
        $paraQuem = self::respostas($tenantId)['para_quem'] ?? null;

        return config("onboarding.para_quem.{$paraQuem}.nome_do_lead") ?? 'Nome';
    }

    public static function usuariosSugeridos(int $tenantId): ?int
    {
        $tamanho = self::respostas($tenantId)['tamanho'] ?? null;

        return $tamanho === null ? null : config("onboarding.tamanhos.{$tamanho}.minimo");
    }

    /** Pela resposta; sem ela, por usuários ativos e convites pendentes. */
    public static function equipeComMaisDeUm(int $tenantId): bool
    {
        $sugeridos = self::usuariosSugeridos($tenantId);
        if ($sugeridos !== null) {
            return $sugeridos > 1;
        }

        $ativos = User::doTenant($tenantId)->ativos()->count();
        $convites = Convite::withoutGlobalScopes()->where('tenant_id', $tenantId)->pendentes()->count();

        return $ativos + $convites > 1;
    }

    /** Quem já usou CRM recebe a versão curta do passo a passo. */
    public static function versaoDoTour(array $respostas): string
    {
        return in_array($respostas['experiencia'] ?? null, ['varios', 'alguns'], true) ? 'curta' : 'completa';
    }
}
