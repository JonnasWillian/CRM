<?php

namespace App\Observers;

use App\Models\EstagioHistorico;
use App\Models\Usuario;
use App\Support\ActivityLog\LeadActivity;

/**
 * Observa o próprio lead: criação, mudança de estágio e mudança de funil.
 *
 * Este observer é também o único lugar que grava EstagioHistorico. O mesmo
 * bloco vivia duplicado em Userarios::update() e Userarios::patchEstagio();
 * aqui ele cobre qualquer caminho que altere estágio ou funil.
 */
class UsuarioObserver
{
    public function created(Usuario $usuario): void
    {
        LeadActivity::registrar($usuario, $usuario->id, 'lead_criado', 'Lead cadastrado no sistema');
    }

    public function updated(Usuario $usuario): void
    {
        $mudouEstagio = $usuario->wasChanged('estagio_id');
        $mudouFunil = $usuario->wasChanged('funil_id');

        if (! $mudouEstagio && ! $mudouFunil) {
            return;
        }

        $estagioAnterior = $usuario->getOriginal('estagio_id');
        $funilAnterior = $usuario->getOriginal('funil_id');

        // Uma troca de funil é sempre também uma troca de estágio (o lead cai
        // num estágio do funil de destino), então as duas cabem numa linha só
        // de histórico. O que muda é como o evento é rotulado.
        EstagioHistorico::create([
            'usuario_id' => $usuario->id,
            'estagio_anterior_id' => $estagioAnterior,
            'estagio_novo_id' => $usuario->estagio_id,
            'funil_anterior_id' => $funilAnterior,
            'funil_novo_id' => $usuario->funil_id,
        ]);

        // As chaves gravadas aqui mudaram de nome junto com as colunas. Linhas
        // JÁ existentes no activity log seguem com `tag_id_anterior`/`tag_id_novo`
        // — histórico é imutável e não é reescrito.
        LeadActivity::registrar(
            $usuario,
            $usuario->id,
            $mudouFunil ? 'funil_alterado' : 'status_alterado',
            $mudouFunil ? 'Lead movido de funil' : 'Estágio do lead alterado',
            [
                'estagio_anterior_id' => $estagioAnterior,
                'estagio_novo_id' => $usuario->estagio_id,
                'funil_anterior_id' => $funilAnterior,
                'funil_novo_id' => $usuario->funil_id,
            ],
        );
    }
}
