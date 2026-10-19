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
        self::sincronizarRecorrencia($projeto);

        if (! $projeto->isDirty('status_id')) {
            return;
        }

        $status = $projeto->status_id ? DB::table('status')->where('id', $projeto->status_id)->first(['is_won', 'is_lost']) : null;

        if (! $status?->is_won) {
            $projeto->fechado_em = null;
        } elseif ($projeto->fechado_em === null) {
            $projeto->fechado_em = now();
        }

        // Contrato recorrente perdido = cancelado: a contagem de meses para aqui.
        if (! $status?->is_lost) {
            $projeto->cancelado_em = null;
        } elseif ($projeto->cancelado_em === null) {
            $projeto->cancelado_em = now();
        }
    }

    /**
     * Recorrente: o valor do projeto é o total do contrato e o fim é o fim do
     * último período — assim as somas que já existem (projetos em aberto,
     * ganhos, valor da perda, Pipeline) continuam certas sem saber de meses.
     */
    public static function sincronizarRecorrencia(Projeto $projeto): void
    {
        if (! $projeto->ehRecorrente()) {
            $projeto->valor_mensal = null;
            $projeto->duracao_meses = null;

            return;
        }

        $projeto->parcelas = false;
        $projeto->qtd_parcelas = null;
        $projeto->unsetRelation('aditivos');
        $cronograma = $projeto->cronograma();
        if ($cronograma !== null) {
            $projeto->preco = $cronograma->valorTotal();
            $projeto->data_final = $cronograma->fim()->toDateString();
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
