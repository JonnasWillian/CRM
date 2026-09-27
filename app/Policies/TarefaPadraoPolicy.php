<?php

namespace App\Policies;

use App\Models\TarefaPadrao;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Dono direto, em `tarefa_padroes.user_id`.
 *
 * Aqui `leads.view-all` NÃO passa: modelo de tarefa é ferramenta pessoal do
 * agente, não dado de carteira. Enxergar a carteira da equipe não é motivo
 * para editar o modelo de trabalho de um colega.
 */
class TarefaPadraoPolicy
{
    public function update(User $user, TarefaPadrao $modelo): Response
    {
        return $modelo->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    public function delete(User $user, TarefaPadrao $modelo): Response
    {
        return $this->update($user, $modelo);
    }
}
