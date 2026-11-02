<?php

namespace App\Policies;

use App\Models\Fatura;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Fatura não tem TenantScope (a rotina diária percorre todas as empresas):
 * a de outra empresa precisa ser 404 aqui. Pagar e acompanhar exige
 * configuracoes.manage, como o resto da tela de assinatura.
 */
class FaturaPolicy
{
    public function pagar(User $user, Fatura $fatura): Response
    {
        if ($fatura->tenant_id !== $user->tenant_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('configuracoes.manage') ? Response::allow() : Response::deny();
    }
}
