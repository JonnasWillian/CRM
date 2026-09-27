<?php

namespace App\Policies;

use App\Models\arquivo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Um salto até o dono: arquivo -> usuario.user_id. */
class ArquivoPolicy
{
    public function view(User $user, arquivo $arquivo): Response
    {
        return $this->doDono($user, $arquivo);
    }

    public function delete(User $user, arquivo $arquivo): Response
    {
        return $this->doDono($user, $arquivo);
    }

    private function doDono(User $user, arquivo $arquivo): Response
    {
        if ($user->can('leads.view-all')) {
            return Response::allow();
        }

        return $arquivo->usuario?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
