<?php

namespace App\Policies;

use App\Models\ProjetoAnexo;
use App\Models\User;
use App\Policies\Concerns\DonoDoLead;
use Illuminate\Auth\Access\Response;

/** Dois saltos: anexo -> projeto -> usuario.user_id. */
class ProjetoAnexoPolicy
{
    use DonoDoLead;

    public function view(User $user, ProjetoAnexo $anexo): Response
    {
        return $this->doDono($user, $anexo);
    }

    public function delete(User $user, ProjetoAnexo $anexo): Response
    {
        return $this->doDono($user, $anexo);
    }

    private function doDono(User $user, ProjetoAnexo $anexo): Response
    {
        return $this->doDonoDoLead($user, $anexo->projeto?->usuario);
    }
}
