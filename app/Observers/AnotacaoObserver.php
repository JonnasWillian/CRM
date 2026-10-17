<?php

namespace App\Observers;

use App\Models\Anotacao;
use App\Support\ActivityLog\LeadActivity;
use App\Support\Reports\MetricasCache;
use Illuminate\Support\Facades\DB;

class AnotacaoObserver
{
    public function created(Anotacao $anotacao): void
    {
        LeadActivity::registrar($anotacao, $anotacao->usuario_id, 'anotacao', 'Anotação adicionada', [
            'descricao' => $anotacao->descricao,
        ]);

        // Anotar é contato. Via query builder, e não pelo model: salvar o lead
        // dispararia UsuarioObserver::updated e um evento que não aconteceu.
        DB::table('usuarios')->where('id', $anotacao->usuario_id)->update(['ultimo_contato_em' => now()]);
        MetricasCache::invalidar($anotacao->tenant_id);
    }

    public function deleted(Anotacao $anotacao): void
    {
        LeadActivity::registrar($anotacao, $anotacao->usuario_id, 'anotacao_removida', 'Anotação removida');
    }
}
