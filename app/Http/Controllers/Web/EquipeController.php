<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\EquipeController as EquipeApi;
use App\Http\Controllers\Controller;
use App\Services\Equipe\Membros;
use Inertia\Inertia;
use Inertia\Response;

class EquipeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Configuracoes/Equipe', [
            'membros' => EquipeApi::membros(),
            'convites' => EquipeApi::convites(),
            'papeis' => Membros::PAPEIS,
        ]);
    }
}
