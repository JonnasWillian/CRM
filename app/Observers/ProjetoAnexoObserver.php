<?php

namespace App\Observers;

use App\Models\ProjetoAnexo;
use App\Support\ActivityLog\LeadActivity;

/**
 * Dois saltos até o dono: anexo -> projeto -> lead.
 */
class ProjetoAnexoObserver
{
    public function created(ProjetoAnexo $anexo): void
    {
        LeadActivity::registrar($anexo, static::leadId($anexo), 'projeto_anexo', 'Anexo de projeto adicionado', [
            'nome' => $anexo->nome ?: 'Arquivo sem nome',
            'projeto_nome' => $anexo->projeto?->nome,
        ]);
    }

    public function deleted(ProjetoAnexo $anexo): void
    {
        LeadActivity::registrar($anexo, static::leadId($anexo), 'projeto_anexo_removido', 'Anexo de projeto removido', [
            'nome' => $anexo->nome ?: 'Arquivo sem nome',
        ]);
    }

    private static function leadId(ProjetoAnexo $anexo): ?int
    {
        return $anexo->projeto?->usuario_id;
    }
}
