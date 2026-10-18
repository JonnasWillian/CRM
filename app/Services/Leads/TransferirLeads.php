<?php

namespace App\Services\Leads;

use App\Models\User;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Trocar o responsável de leads. Lead a lead pelo model, para o
 * UsuarioObserver registrar `responsavel_alterado` no histórico de cada um
 * (tarefas, projetos e anotações seguem o lead: a visibilidade é pelo dono
 * do lead). Quem já é do destino é ignorado e contado.
 */
class TransferirLeads
{
    public const LOTE = 100;

    public function umLead(Usuario $lead, User $destino): bool
    {
        if ($lead->user_id === $destino->id) {
            return false;
        }

        $lead->user_id = $destino->id;
        $lead->save();

        return true;
    }

    /** @return array{afetados:int, ignorados:int} */
    public function varios(Builder $selecao, int $pedidos, User $destino): array
    {
        $afetados = 0;

        $selecao->where('usuarios.user_id', '!=', $destino->id)
            ->chunkById(self::LOTE, function (Collection $leads) use (&$afetados, $destino) {
                foreach ($leads as $lead) {
                    $this->umLead($lead, $destino) && $afetados++;
                }
            }, 'usuarios.id', 'id');

        return ['afetados' => $afetados, 'ignorados' => max(0, $pedidos - $afetados)];
    }

    /** A carteira inteira de um membro, lixeira incluída (o lead restaurado não pode voltar para um dono desativado). */
    public function carteira(User $origem, User $destino): int
    {
        return $this->varios(Usuario::withTrashed()->where('usuarios.user_id', $origem->id)->orderBy('usuarios.id'), 0, $destino)['afetados'];
    }
}
