<?php

namespace App\Observers;

use App\Models\arquivo;
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
}
