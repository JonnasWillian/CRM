<?php

namespace App\Http\Controllers;

use App\Models\Anotacao;
use App\Models\arquivo as ArquivoModel;
use App\Models\EstagioHistorico;
use App\Models\Projeto;
use App\Models\ProjetoAnexo;
use App\Models\ProjetoAnotacao;
use App\Models\Tarefa;
use App\Models\Usuario;

/**
 * O que sobrou do controller original de leads: o timeline deprecado
 * (só o teste de paridade; sai na Fase 5).
 */
class Userarios extends Controller
{
    /**
     * @deprecated Substituído por LeadAtividadeController::index (activity
     *             log paginado). Mantido só para o teste de paridade; remover
     *             depois que o endpoint novo rodar em produção (spec
     *             2026-09-20-activity-log-design, fase 4).
     */
    public function timeline(Usuario $usuario)
    {
        $this->authorize('view', $usuario);

        $id = $usuario->id;

        $events = [];

        $events[] = [
            'tipo' => 'lead_criado',
            'descricao' => 'Lead cadastrado no sistema',
            'data' => $usuario->created_at,
        ];

        foreach (Anotacao::where('usuario_id', $id)->get() as $a) {
            $events[] = [
                'tipo' => 'anotacao',
                'descricao' => $a->descricao,
                'data' => $a->created_at,
            ];
        }

        foreach (ArquivoModel::where('usuario_id', $id)->get() as $f) {
            $events[] = [
                'tipo' => 'arquivo',
                'nome' => $f->nome ?: 'Arquivo sem nome',
                'data' => $f->created_at,
            ];
        }

        foreach (Projeto::where('usuario_id', $id)->get() as $p) {
            $events[] = [
                'tipo' => 'projeto',
                'nome' => $p->nome,
                'data' => $p->created_at,
            ];

            foreach (ProjetoAnotacao::where('projeto_id', $p->id)->get() as $pa) {
                $events[] = [
                    'tipo' => 'projeto_anotacao',
                    'descricao' => $pa->descricao,
                    'projeto_nome' => $p->nome,
                    'data' => $pa->created_at,
                ];
            }

            foreach (ProjetoAnexo::where('projeto_id', $p->id)->get() as $pf) {
                $events[] = [
                    'tipo' => 'projeto_anexo',
                    'nome' => $pf->nome ?: 'Arquivo sem nome',
                    'projeto_nome' => $p->nome,
                    'data' => $pf->created_at,
                ];
            }
        }

        foreach (EstagioHistorico::with(['estagioAnterior', 'estagioNovo'])->where('usuario_id', $id)->get() as $h) {
            $events[] = [
                'tipo' => 'status_alterado',
                'estagio_anterior' => $h->estagioAnterior->descricao ?? '—',
                'estagio_novo' => $h->estagioNovo->descricao ?? '—',
                'data' => $h->created_at,
            ];
        }

        foreach (Tarefa::where('usuario_id', $id)->get() as $t) {
            $events[] = [
                'tipo' => 'tarefa_criada',
                'titulo' => $t->titulo,
                'data' => $t->created_at,
            ];
            if ($t->concluido && $t->concluido_em) {
                $events[] = [
                    'tipo' => 'tarefa_concluida',
                    'titulo' => $t->titulo,
                    'data' => $t->concluido_em,
                ];
            }
        }

        usort($events, fn ($a, $b) => $b['data'] <=> $a['data']);

        return response()->json($events);
    }
}
