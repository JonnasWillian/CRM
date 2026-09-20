<?php

namespace App\Observers;

use App\Models\ProjetoAnotacao;
use App\Support\ActivityLog\LeadActivity;

/**
 * Dois saltos até o dono: anotação -> projeto -> lead.
 */
class ProjetoAnotacaoObserver
{
    public function created(ProjetoAnotacao $anotacao): void
    {
        LeadActivity::registrar($anotacao, static::leadId($anotacao), 'projeto_anotacao', 'Anotação de projeto adicionada', [
            'descricao' => $anotacao->descricao,
            'projeto_nome' => $anotacao->projeto?->nome,
        ]);
    }

    public function deleted(ProjetoAnotacao $anotacao): void
    {
        LeadActivity::registrar($anotacao, static::leadId($anotacao), 'projeto_anotacao_removida', 'Anotação de projeto removida');
    }

    private static function leadId(ProjetoAnotacao $anotacao): ?int
    {
        return $anotacao->projeto?->usuario_id;
    }
}
