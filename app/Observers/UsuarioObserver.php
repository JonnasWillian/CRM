<?php

namespace App\Observers;

use App\Models\Usuario;
use App\Models\UsuarioTagHistorico;
use App\Support\ActivityLog\LeadActivity;

/**
 * Observa o próprio lead: criação e mudança de estágio.
 *
 * Este observer é também o único lugar que grava UsuarioTagHistorico. O mesmo
 * bloco vivia duplicado em Userarios::update() e Userarios::patchTag(); aqui
 * ele cobre qualquer caminho que altere tag_id, não só aqueles dois.
 */
class UsuarioObserver
{
    public function created(Usuario $usuario): void
    {
        LeadActivity::registrar($usuario, $usuario->id, 'lead_criado', 'Lead cadastrado no sistema');
    }

    public function updated(Usuario $usuario): void
    {
        if (! $usuario->wasChanged('tag_id')) {
            return;
        }

        $anterior = $usuario->getOriginal('tag_id');

        UsuarioTagHistorico::create([
            'usuario_id' => $usuario->id,
            'tag_id_anterior' => $anterior,
            'tag_id_novo' => $usuario->tag_id,
        ]);

        LeadActivity::registrar($usuario, $usuario->id, 'status_alterado', 'Estágio do lead alterado', [
            'tag_id_anterior' => $anterior,
            'tag_id_novo' => $usuario->tag_id,
        ]);
    }
}
