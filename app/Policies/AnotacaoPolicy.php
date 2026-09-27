<?php

namespace App\Policies;

use App\Models\Anotacao;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Um salto até o dono: anotação -> usuario.user_id. */
class AnotacaoPolicy
{
    public function view(User $user, Anotacao $anotacao): Response
    {
        return $this->doDono($user, $anotacao);
    }

    public function update(User $user, Anotacao $anotacao): Response
    {
        return $this->doDono($user, $anotacao);
    }

    public function delete(User $user, Anotacao $anotacao): Response
    {
        return $this->doDono($user, $anotacao);
    }

    private function doDono(User $user, Anotacao $anotacao): Response
    {
        if ($user->can('leads.view-all')) {
            return Response::allow();
        }

        return $anotacao->usuario?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
