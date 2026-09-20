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
}
