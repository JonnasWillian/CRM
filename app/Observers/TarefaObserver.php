<?php

namespace App\Observers;

use App\Models\Tarefa;
use App\Support\ActivityLog\LeadActivity;

class TarefaObserver
{
    public function created(Tarefa $tarefa): void
    {
        LeadActivity::registrar($tarefa, $tarefa->usuario_id, 'tarefa_criada', 'Tarefa criada', [
            'titulo' => $tarefa->titulo,
        ]);
    }

    /**
     * Conclusão é evento próprio, separado da criação: a timeline antiga
     * mostrava os dois, em datas diferentes (created_at e concluido_em).
     */
    public function updated(Tarefa $tarefa): void
    {
        if (! $tarefa->wasChanged('concluido') || ! $tarefa->concluido) {
            return;
        }

        LeadActivity::registrar($tarefa, $tarefa->usuario_id, 'tarefa_concluida', 'Tarefa concluída', [
            'titulo' => $tarefa->titulo,
        ]);
    }
}
