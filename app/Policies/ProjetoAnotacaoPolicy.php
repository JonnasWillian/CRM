<?php

namespace App\Policies;

use App\Models\ProjetoAnotacao;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Dois saltos: anotação -> projeto -> usuario.user_id.
 */
class ProjetoAnotacaoPolicy
{
    public function update(User $user, ProjetoAnotacao $anotacao): Response
    {
        return $this->doDono($user, $anotacao);
    }

    public function delete(User $user, ProjetoAnotacao $anotacao): Response
    {
        return $this->doDono($user, $anotacao);
    }

    private function doDono(User $user, ProjetoAnotacao $anotacao): Response
    {
        if ($user->can('leads.view-all')) {
            return Response::allow();
        }

        return $anotacao->projeto?->usuario?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
