<?php

namespace App\Observers;

use App\Models\Arquivo;
use App\Services\Arquivos\ArquivoService;
use App\Support\ActivityLog\LeadActivity;

class ArquivoObserver
{
    public function created(Arquivo $arquivo): void
    {
        LeadActivity::registrar($arquivo, $arquivo->usuario_id, 'arquivo', 'Arquivo anexado', [
            'nome' => $arquivo->nome ?: 'Arquivo sem nome',
        ]);
    }

    public function deleted(Arquivo $arquivo): void
    {
        LeadActivity::registrar($arquivo, $arquivo->usuario_id, 'arquivo_removido', 'Arquivo removido', [
            'nome' => $arquivo->nome ?: 'Arquivo sem nome',
        ]);
    }

    /**
     * O soft delete mantém o arquivo (restaurável). Só a exclusão definitiva
     * o remove — e é o único ponto do sistema que faz isso.
     */
    public function forceDeleted(Arquivo $arquivo): void
    {
        app(ArquivoService::class)->remover($arquivo->local);
    }
}
