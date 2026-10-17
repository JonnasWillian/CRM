<?php

namespace App\Queries\Tarefas;

use App\Models\Tarefa;
use App\Models\User;
use App\Models\Usuario;

/**
 * Resumo para o dashboard: totais de atrasadas/hoje/próximos 7 dias e os
 * 10 primeiros itens dos dois primeiros grupos. Cinco consultas, nenhuma
 * lista inteira.
 */
class ResumoDeTarefas
{
    public const ITENS = 10;

    public function __invoke(User $user): array
    {
        $hoje = today();
        $pendentes = fn () => Tarefa::whereIn('usuario_id', Usuario::idsVisiveisPara($user))->where('concluido', false);
        $itens = fn ($q) => $q->with('lead:id,nome')->orderBy('data_limite')->orderBy('id')->limit(self::ITENS)->get();

        $atrasadas = $pendentes()->where('data_limite', '<', $hoje->toDateString());
        $deHoje = $pendentes()->where('data_limite', $hoje->toDateString());
        $proximos = $pendentes()->whereBetween('data_limite', [$hoje->copy()->addDay()->toDateString(), $hoje->copy()->addDays(7)->toDateString()]);

        return [
            'atrasadas' => ['total' => (clone $atrasadas)->count(), 'itens' => $itens($atrasadas)],
            'hoje' => ['total' => (clone $deHoje)->count(), 'itens' => $itens($deHoje)],
            'proximos_7_dias' => ['total' => $proximos->count()],
        ];
    }
}
