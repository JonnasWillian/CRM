<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Models\Usuario;
use Illuminate\Auth\Access\Response;

/**
 * A regra das oito policies, num lugar só: quem tem `leads.view-all` passa
 * dentro do tenant; quem não tem, passa só no que é seu.
 *
 * Com uma condição antes, que é o motivo de este trait existir: sem lead não
 * há acesso — nem para quem tem view-all. `$filho->usuario` é null quando o
 * lead está na lixeira (SoftDeletes esconde a relação), e a ordem antiga
 * (view-all primeiro) deixava o gestor editar anotação, tarefa e projeto de um
 * lead excluído, que ele já não enxerga em lugar nenhum.
 */
trait DonoDoLead
{
    protected function doDonoDoLead(User $user, ?Usuario $lead): Response
    {
        if ($lead === null) {
            return Response::denyAsNotFound();
        }

        return $user->can('leads.view-all') || $lead->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
