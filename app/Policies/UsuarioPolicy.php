<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Usuario;
use Illuminate\Auth\Access\Response;

/**
 * Lead: o dono está direto na linha, em `usuarios.user_id`.
 *
 * Regra comum a todas as policies deste sistema, em uma frase: quem tem
 * `leads.view-all` passa dentro do tenant; quem não tem, passa só no que é seu.
 * O TenantScope já garante que nada de outro tenant chega até aqui.
 *
 * Negativas devolvem 404 e não 403: 403 confirma que o id existe, e isso
 * deixaria enumerar a carteira dos colegas um id por vez.
 */
class UsuarioPolicy
{
    public function view(User $user, Usuario $usuario): Response
    {
        return $this->doDono($user, $usuario);
    }

    public function update(User $user, Usuario $usuario): Response
    {
        return $this->doDono($user, $usuario);
    }

    public function delete(User $user, Usuario $usuario): Response
    {
        return $this->doDono($user, $usuario);
    }

    private function doDono(User $user, Usuario $usuario): Response
    {
        if ($user->can('leads.view-all') || $usuario->user_id === $user->id) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }
}
