<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Requests\UsuarioRequest;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

use App\Models\Usuario;
use App\Models\Tags;
use App\Models\Anotacao;
use App\Models\arquivo AS ArquivoModel;
use App\Models\Projeto;
use App\Models\ProjetoAnotacao;
use App\Models\ProjetoAnexo;
use App\Models\UsuarioTagHistorico;
use App\Models\Tarefa;

class Userarios extends Controller
{
    public function view(Request $request)
    {
        $usuarios = Usuario::where('user_id', $request->user_id)
            ->with('tag')
            ->addSelect([
                '*',
                'tem_projeto' => Projeto::whereColumn('usuario_id', 'usuarios.id')
                    ->selectRaw('COUNT(*) > 0'),
                'tem_projeto_aberto' => Projeto::whereColumn('usuario_id', 'usuarios.id')
                    ->whereNotIn('status_id', [5, 6])
                    ->selectRaw('COUNT(*) > 0'),
            ])
            ->get();

        return response()->json($usuarios);
    }

    public function tags()
    {
        $tags = Tags::get();
        
        return response()->json($tags);
    }


    public function create(UsuarioRequest $request)
    {
        try {
            Usuario::create($request->validated());

            return response()->json(['message' => 'Usuário cadastrado com sucesso'], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        }
    }

    public function viewUsuario($id)
    {
        $usuario = Usuario::where('id', $id)->get();

        return response()->json($usuario);
    }

    public function update(UsuarioRequest $request, $id)
    {
        try {
            $usuario = Usuario::findOrFail($id);

            $tagAnterior = $usuario->tag_id;
            $usuario->update($request->validated());

            if ($tagAnterior !== $usuario->tag_id) {
                UsuarioTagHistorico::create([
                    'usuario_id'      => $id,
                    'tag_id_anterior' => $tagAnterior,
                    'tag_id_novo'     => $usuario->tag_id,
                ]);
            }

            return response()->json(['message' => 'Usuário atualizado com sucesso'], 200);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Usuário não encontrado'], 404);
        }
    }

    public function timeline($id)
    {
        try {
            $usuario = Usuario::findOrFail($id);
            $events = [];

            $events[] = [
                'tipo'      => 'lead_criado',
                'descricao' => 'Lead cadastrado no sistema',
                'data'      => $usuario->created_at,
            ];

            foreach (Anotacao::where('usuario_id', $id)->get() as $a) {
                $events[] = [
                    'tipo'     => 'anotacao',
                    'descricao' => $a->descricao,
                    'data'     => $a->created_at,
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
                        'tipo'         => 'projeto_anotacao',
                        'descricao'    => $pa->descricao,
                        'projeto_nome' => $p->nome,
                        'data'         => $pa->created_at,
                    ];
                }

                foreach (ProjetoAnexo::where('projeto_id', $p->id)->get() as $pf) {
                    $events[] = [
                        'tipo'         => 'projeto_anexo',
                        'nome'         => $pf->nome ?: 'Arquivo sem nome',
                        'projeto_nome' => $p->nome,
                        'data'         => $pf->created_at,
                    ];
                }
            }

            foreach (UsuarioTagHistorico::with(['tagAnterior', 'tagNovo'])->where('usuario_id', $id)->get() as $h) {
                $events[] = [
                    'tipo'          => 'status_alterado',
                    'tag_anterior'  => $h->tagAnterior->descricao ?? '—',
                    'tag_novo'      => $h->tagNovo->descricao ?? '—',
                    'data'          => $h->created_at,
                ];
            }

            foreach (Tarefa::where('usuario_id', $id)->get() as $t) {
                $events[] = [
                    'tipo'   => 'tarefa_criada',
                    'titulo' => $t->titulo,
                    'data'   => $t->created_at,
                ];
                if ($t->concluido && $t->concluido_em) {
                    $events[] = [
                        'tipo'   => 'tarefa_concluida',
                        'titulo' => $t->titulo,
                        'data'   => $t->concluido_em,
                    ];
                }
            }

            usort($events, fn($a, $b) => $b['data'] <=> $a['data']);

            return response()->json($events);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Lead não encontrado'], 404);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $usuario = Usuario::findOrFail($id);
            $usuario->delete();

            return response()->json(['message' => 'Usuário deletado com sucesso'], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Usuário não encontrado'], 201);
        }
    }

    public function viewAnotacao($id)
    {
        $anotacoes = Anotacao::where('usuario_id', $id)->get();

        return response()->json($anotacoes);
    }


    public function createAnotacao(Request $request)
    {
        try {
            $validateRequest = $request->validate([
                'descricao' => 'required|string',
                'usuario_id' => 'required',
            ]);

            Anotacao::create($validateRequest);

            return response()->json(['message' => 'Anotação cadastrada com sucesso'], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        }
    }


    public function updateAnotacao(Request $request, $id)
    {
        try {
            $anotacao = Anotacao::findOrFail($id);
            
            $validateRequest = $request->validate([
                'descricao' => 'required|string',
            ]);

            $anotacao->update($validateRequest);

            return response()->json(['message' => 'Anotação atualizada com sucesso'], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Anotação não encontrado'], 201);
        }
    }

    public function destroyAnotacao(Request $request, $id)
    {
        try {
            $anotacao = Anotacao::findOrFail($id);
            $anotacao->delete();

            return response()->json(['message' => 'Anotação deletada com sucesso'], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json([
                'erros' => $error->errors()
            ], 422);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Anotação não encontrada'], 201);
        }
    }

    public function kanban(Request $request)
    {
        $userId = $request->user_id;
        $tags   = Tags::orderBy('ordem')->get();

        $leads = Usuario::where('user_id', $userId)
            ->addSelect([
                '*',
                'ultimo_contato' => Anotacao::whereColumn('usuario_id', 'usuarios.id')
                    ->orderByDesc('created_at')
                    ->select('created_at')
                    ->limit(1),
                'valor_projetos' => Projeto::whereColumn('usuario_id', 'usuarios.id')
                    ->whereHas('status', fn ($q) => $q->where('is_won', false)->where('is_lost', false))
                    ->selectRaw('COALESCE(SUM(preco), 0)'),
            ])
            ->get()
            ->map(fn($u) => [
                'id'             => $u->id,
                'nome'           => $u->nome,
                'email'          => $u->email,
                'telefone'       => $u->telefone,
                'descricao'      => $u->descricao,
                'tag_id'         => $u->tag_id,
                'ultimo_contato' => $u->ultimo_contato ?? $u->updated_at,
                'valor_projetos' => (float) ($u->valor_projetos ?? 0),
            ]);

        return response()->json(['tags' => $tags, 'leads' => $leads]);
    }

    public function patchTag(Request $request, $id)
    {
        try {
            $usuario = Usuario::findOrFail($id);
            $tagAnterior = $usuario->tag_id;

            $validated = $request->validate(['tag_id' => 'required|exists:tags,id']);
            $usuario->update($validated);

            if ($tagAnterior !== $usuario->tag_id) {
                UsuarioTagHistorico::create([
                    'usuario_id'      => $id,
                    'tag_id_anterior' => $tagAnterior,
                    'tag_id_novo'     => $usuario->tag_id,
                ]);
            }

            return response()->json(['message' => 'Tag atualizada']);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Lead não encontrado'], 404);
        }
    }

    public function kanbanSettings(Request $request)
    {
        try {
            $user = \App\Models\User::findOrFail($request->user_id);
            $user->update(['kanban_default_tag_id' => $request->default_tag_id]);
            return response()->json(['message' => 'Preferência salva']);
        } catch (\Exception $error) {
            return response()->json(['error' => 'Usuário não encontrado'], 404);
        }
    }

    public function metricas(Request $request)
    {
        $userId = $request->user_id;
        $leadIds = Usuario::where('user_id', $userId)->pluck('id');
        $leadsAtivos     = Usuario::where('user_id', $userId)->whereHas('tag', fn ($q) => $q->where('is_active', true))->count();
        $leadsArquivados = Usuario::where('user_id', $userId)->whereHas('tag', fn ($q) => $q->where('is_active', false))->count();
        $leads30Dias     = Usuario::where('user_id', $userId)->where('created_at', '>=', now()->subDays(30))->count();
        $valorAberto = Projeto::whereIn('usuario_id', $leadIds)->whereHas('status', fn ($q) => $q->where('is_won', false)->where('is_lost', false))->sum('preco') ?? 0;
        $valorFechadoMes = Projeto::whereIn('usuario_id', $leadIds)->whereHas('status', fn ($q) => $q->where('is_won', true))->whereMonth('updated_at', now()->month)->whereYear('updated_at', now()->year)->sum('preco') ?? 0;
        $leadsPorTag = Usuario::where('user_id', $userId)->select('tag_id', DB::raw('count(*) as total'))->with('tag')->groupBy('tag_id')->get()
            ->map(fn($row) => [
                'id'        => $row->tag_id,
                'descricao' => $row->tag->descricao ?? 'Sem tag',
                'total'     => (int) $row->total,
            ]);
        $totalLeads      = $leadIds->count();
        $leadsComProjeto = $leadIds->isNotEmpty()
            ? Projeto::whereIn('usuario_id', $leadIds)->distinct('usuario_id')->count('usuario_id')
            : 0;
        $taxaConversao   = $totalLeads > 0 ? round($leadsComProjeto / $totalLeads * 100, 1) : 0;

        return response()->json([
            'leads_ativos'           => $leadsAtivos,
            'leads_arquivados'       => $leadsArquivados,
            'leads_30_dias'          => $leads30Dias,
            'valor_projetos_abertos' => (float) $valorAberto,
            'valor_fechado_mes'      => (float) $valorFechadoMes,
            'leads_por_tag'          => $leadsPorTag,
            'taxa_conversao'         => $taxaConversao,
            'total_com_projeto'      => $leadsComProjeto,
            'total_leads'            => $totalLeads,
        ]);
    }
}
