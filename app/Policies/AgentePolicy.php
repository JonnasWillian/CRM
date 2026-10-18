<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Membro da equipe (User): mesma empresa ou 404; gerenciar exige agentes.manage. */
class AgentePolicy
{
    public function gerenciar(User $user, User $membro): Response
    {
        if ($membro->tenant_id !== $user->tenant_id) {
            return Response::denyAsNotFound();
        }

        return $user->can('agentes.manage') ? Response::allow() : Response::deny();
    }
}
