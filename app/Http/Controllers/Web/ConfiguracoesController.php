<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\FunilController as FunilApi;
use App\Http\Controllers\Api\MotivoPerdaController as MotivoApi;
use App\Http\Controllers\Controller;
use App\Models\TarefaPadrao;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Páginas de configuração: as listas chegam por props e as escritas vão pela API. */
class ConfiguracoesController extends Controller
{
    public function funis(): Response
    {
        return Inertia::render('Configuracoes/Funis', ['funis' => FunilApi::todos(incluirArquivados: true)]);
    }

    public function motivosPerda(): Response
    {
        return Inertia::render('Configuracoes/MotivosPerda', ['motivos' => MotivoApi::todos(incluirArquivados: true)]);
    }

    /** Modelos de tarefa são do próprio agente (user_id = quem está logado). */
    public function modelosTarefa(Request $request): Response
    {
        return Inertia::render('Configuracoes/ModelosTarefa', [
            'modelos' => TarefaPadrao::where('user_id', $request->user()->id)->orderBy('titulo')->orderBy('id')->get(['id', 'titulo', 'anotacao', 'prazo_dias']),
        ]);
    }
}
