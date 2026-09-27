<?php

namespace App\Policies;

use App\Models\Projeto;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Um salto até o dono: projeto -> usuario.user_id.
 */
class ProjetoPolicy
{
    public function view(User $user, Projeto $projeto): Response
    {
        return $this->doDono($user, $projeto);
    }

    public function update(User $user, Projeto $projeto): Response
    {
        return $this->doDono($user, $projeto);
    }

    public function delete(User $user, Projeto $projeto): Response
    {
        return $this->doDono($user, $projeto);
    }

    private function doDono(User $user, Projeto $projeto): Response
    {
        if ($user->can('leads.view-all')) {
            return Response::allow();
        }

        return $projeto->usuario?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
