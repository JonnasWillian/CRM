<?php

namespace App\Policies;

use App\Models\Tarefa;
use App\Models\User;
use App\Policies\Concerns\DonoDoLead;
use Illuminate\Auth\Access\Response;

/** Um salto até o dono: tarefa -> lead.user_id. */
class TarefaPolicy
{
    use DonoDoLead;

    public function view(User $user, Tarefa $tarefa): Response
    {
        return $this->doDono($user, $tarefa);
    }

    public function update(User $user, Tarefa $tarefa): Response
    {
        return $this->doDono($user, $tarefa);
    }

    public function delete(User $user, Tarefa $tarefa): Response
    {
        return $this->doDono($user, $tarefa);
    }

    private function doDono(User $user, Tarefa $tarefa): Response
    {
        return $this->doDonoDoLead($user, $tarefa->lead);
    }
}
