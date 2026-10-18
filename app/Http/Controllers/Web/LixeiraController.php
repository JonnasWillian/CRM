<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\LixeiraController as LixeiraApi;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListagemDaLixeiraRequest;
use App\Queries\Lixeira\ListagemDaLixeira;
use Inertia\Inertia;
use Inertia\Response;

class LixeiraController extends Controller
{
    public function index(ListagemDaLixeiraRequest $request, ListagemDaLixeira $lixeira): Response
    {
        return Inertia::render('Lixeira/Index', [
            'lixeira' => LixeiraApi::resposta($request, $lixeira),
            'agentes' => LeadController::agentes($request->user()),
        ]);
    }
}
