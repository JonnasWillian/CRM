<?php

namespace App\Observers;

use App\Models\Anotacao;
use App\Support\ActivityLog\LeadActivity;

class AnotacaoObserver
{
    public function created(Anotacao $anotacao): void
    {
        LeadActivity::registrar($anotacao, $anotacao->usuario_id, 'anotacao', 'Anotação adicionada', [
            'descricao' => $anotacao->descricao,
        ]);
    }

    public function deleted(Anotacao $anotacao): void
    {
        LeadActivity::registrar($anotacao, $anotacao->usuario_id, 'anotacao_removida', 'Anotação removida');
    }
}
