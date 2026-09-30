<?php

namespace App\Policies;

use App\Models\arquivo;
use App\Models\User;
use App\Policies\Concerns\DonoDoLead;
use Illuminate\Auth\Access\Response;

/** Um salto até o dono: arquivo -> usuario.user_id. */
class ArquivoPolicy
{
    use DonoDoLead;

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
        return $this->doDonoDoLead($user, $arquivo->usuario);
    }
}
