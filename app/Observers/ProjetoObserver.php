<?php

namespace App\Observers;

use App\Models\Projeto;
use App\Support\ActivityLog\LeadActivity;
use App\Support\Reports\MetricasCache;
use Illuminate\Support\Facades\DB;

class ProjetoObserver
{
    /**
     * `fechado_em` é o instante em que o projeto entrou num status ganho;
     * sai de ganho, zera. É o que "fechado no período" lê — não updated_at.
     */
    public function saving(Projeto $projeto): void
    {
        if (! $projeto->isDirty('status_id')) {
            return;
        }

        $ganho = $projeto->status_id
            ? (bool) DB::table('status')->where('id', $projeto->status_id)->value('is_won')
            : false;

        if (! $ganho) {
            $projeto->fechado_em = null;
        } elseif ($projeto->fechado_em === null) {
            $projeto->fechado_em = now();
        }
    }

    public function created(Projeto $projeto): void
    {
        LeadActivity::registrar($projeto, $projeto->usuario_id, 'projeto', 'Projeto criado', [
            'nome' => $projeto->nome,
        ]);
        MetricasCache::invalidar($projeto->tenant_id);
    }

    public function updated(Projeto $projeto): void
    {
        MetricasCache::invalidar($projeto->tenant_id);
    }

    public function deleted(Projeto $projeto): void
    {
        MetricasCache::invalidar($projeto->tenant_id);

        if ($projeto->isForceDeleting()) {
            return;
        }

        LeadActivity::registrar($projeto, $projeto->usuario_id, 'projeto_removido', 'Projeto movido para a lixeira', [
            'nome' => $projeto->nome,
        ]);
    }

    public function restored(Projeto $projeto): void
    {
        MetricasCache::invalidar($projeto->tenant_id);
        LeadActivity::registrar($projeto, $projeto->usuario_id, 'projeto_restaurado', 'Projeto restaurado da lixeira', [
            'nome' => $projeto->nome,
        ]);
    }
}
