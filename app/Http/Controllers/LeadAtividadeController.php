<?php

namespace App\Http\Controllers;

use App\Http\Resources\AtividadeResource;
use App\Models\Activity;
use App\Models\Estagio;
use App\Models\ProjetoAnexo;
use App\Models\ProjetoAnotacao;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

/**
 * Histórico de um lead, servido do activity log, no formato do TimelinePanel.
 *
 * Substitui Userarios::timeline() (deprecado). Ver o teste de paridade em
 * tests/Feature/ActivityLog/TimelineParityTest.php, que compara o conteúdo
 * exibido, não só os tipos.
 */
class LeadAtividadeController extends Controller
{
    private const POR_PAGINA = 20;
    private const MAX_POR_PAGINA = 100;

    public function index(Request $request, Usuario $usuario)
    {
        $this->authorize('view', $usuario);

        $porPagina = min(max($request->integer('per_page') ?: self::POR_PAGINA, 1), self::MAX_POR_PAGINA);

        $pagina = Activity::where('lead_id', $usuario->id)
            ->with(['subject' => fn (MorphTo $morph) => $morph->morphWith([
                ProjetoAnotacao::class => ['projeto'],
                ProjetoAnexo::class => ['projeto'],
            ])])
            ->orderByDesc('created_at')
            // Desempate estável: vários eventos podem cair no mesmo segundo.
            ->orderByDesc('id')
            ->paginate($porPagina);

        $ids = $pagina->getCollection()->flatMap(fn ($a) => AtividadeResource::idsDeEstagio($a))->unique()->all();
        $nomes = $ids ? Estagio::withTrashed()->whereIn('id', $ids)->pluck('descricao', 'id')->all() : [];

        $pagina->through(fn (Activity $a) => (new AtividadeResource($a, $nomes))->resolve($request));

        return response()->json($pagina);
    }
}
