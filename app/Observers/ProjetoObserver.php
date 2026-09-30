<?php

namespace App\Observers;

use App\Models\Projeto;
use App\Support\ActivityLog\LeadActivity;

class ProjetoObserver
{
    public function created(Projeto $projeto): void
    {
        LeadActivity::registrar($projeto, $projeto->usuario_id, 'projeto', 'Projeto criado', [
            'nome' => $projeto->nome,
        ]);
    }

    public function deleted(Projeto $projeto): void
    {
        if ($projeto->isForceDeleting()) {
            return;
        }

        LeadActivity::registrar($projeto, $projeto->usuario_id, 'projeto_removido', 'Projeto movido para a lixeira', [
            'nome' => $projeto->nome,
        ]);
    }

    public function restored(Projeto $projeto): void
    {
        LeadActivity::registrar($projeto, $projeto->usuario_id, 'projeto_restaurado', 'Projeto restaurado da lixeira', [
            'nome' => $projeto->nome,
        ]);
    }
}
