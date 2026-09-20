<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Usuario;
use Illuminate\Http\Request;

/**
 * Histórico de um lead, servido do activity log.
 *
 * Substitui Userarios::timeline(), que montava o mesmo histórico em runtime a
 * partir de sete consultas, ordenava em PHP e devolvia tudo de uma vez. Aqui é
 * uma consulta indexada por (tenant_id, lead_id, created_at) e paginada.
 *
 * Enquanto o timeline() antigo existir, os dois convivem — ver o teste de
 * paridade em tests/Feature/ActivityLog/TimelineParityTest.php.
 */
class LeadAtividadeController extends Controller
{
    private const POR_PAGINA = 20;

    public function index(Request $request, Usuario $usuario)
    {
        // Autorização provisória, até as policies do spec de RBAC entrarem:
        // o lead precisa ser da carteira do agente autenticado. 404 em vez de
        // 403 para não confirmar que o lead existe neste tenant.
        abort_unless($usuario->user_id === auth()->id(), 404);

        $atividades = Activity::where('lead_id', $usuario->id)
            ->orderByDesc('created_at')
            // Desempate estável: vários eventos podem cair no mesmo segundo,
            // e sem isto a paginação poderia repetir ou pular registros.
            ->orderByDesc('id')
            ->paginate($request->integer('per_page') ?: self::POR_PAGINA);

        return response()->json($atividades);
    }
}
