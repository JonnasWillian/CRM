<?php

namespace App\Observers;

use App\Models\arquivo;
use App\Services\Arquivos\ArquivoService;
use App\Support\ActivityLog\LeadActivity;

class ArquivoObserver
{
    public function created(arquivo $arquivo): void
    {
        LeadActivity::registrar($arquivo, $arquivo->usuario_id, 'arquivo', 'Arquivo anexado', [
            'nome' => $arquivo->nome ?: 'Arquivo sem nome',
        ]);
    }

    public function deleted(arquivo $arquivo): void
    {
        LeadActivity::registrar($arquivo, $arquivo->usuario_id, 'arquivo_removido', 'Arquivo removido', [
            'nome' => $arquivo->nome ?: 'Arquivo sem nome',
        ]);
    }

    /**
     * O soft delete mantém o arquivo (restaurável). Só a exclusão definitiva
     * o remove — e é o único ponto do sistema que faz isso.
     */
    public function forceDeleted(arquivo $arquivo): void
    {
        app(ArquivoService::class)->remover($arquivo->local);
    }
}
