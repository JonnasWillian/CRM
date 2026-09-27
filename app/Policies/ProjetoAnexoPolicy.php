<?php

namespace App\Policies;

use App\Models\ProjetoAnexo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Dois saltos: anexo -> projeto -> usuario.user_id. */
class ProjetoAnexoPolicy
{
    public function delete(User $user, ProjetoAnexo $anexo): Response
    {
        if ($user->can('leads.view-all')) {
            return Response::allow();
        }

        return $anexo->projeto?->usuario?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
