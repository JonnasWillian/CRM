<?php

namespace App\Policies;

use App\Models\ProjetoAnotacao;
use App\Models\User;
use App\Policies\Concerns\DonoDoLead;
use Illuminate\Auth\Access\Response;

/**
 * Dois saltos: anotação -> projeto -> usuario.user_id.
 */
class ProjetoAnotacaoPolicy
{
    use DonoDoLead;

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
        return $this->doDonoDoLead($user, $anotacao->projeto?->usuario);
    }
}
