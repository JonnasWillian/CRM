<?php

namespace App\Policies;

use App\Models\Projeto;
use App\Models\User;
use App\Policies\Concerns\DonoDoLead;
use Illuminate\Auth\Access\Response;

/**
 * Um salto até o dono: projeto -> usuario.user_id.
 */
class ProjetoPolicy
{
    use DonoDoLead;

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

    public function restore(User $user, Projeto $projeto): Response
    {
        return $this->doDono($user, $projeto);
    }

    private function doDono(User $user, Projeto $projeto): Response
    {
        return $this->doDonoDoLead($user, $projeto->usuario);
    }
}
