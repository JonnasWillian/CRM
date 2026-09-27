<?php

namespace App\Policies;

use App\Models\Tarefa;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Um salto até o dono: tarefa -> lead.user_id. */
class TarefaPolicy
{
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
        if ($user->can('leads.view-all')) {
            return Response::allow();
        }

        return $tarefa->lead?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
