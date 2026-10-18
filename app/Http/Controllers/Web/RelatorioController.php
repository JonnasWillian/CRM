<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\PerdaRelatorioController as PerdasApi;
use App\Http\Controllers\Controller;
use App\Services\Perdas\RelatorioDePerdas;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Relatórios (leads.view-all): "Por que perdemos" com período e tipo na URL. */
class RelatorioController extends Controller
{
    public function perdas(Request $request, RelatorioDePerdas $relatorio): Response
    {
        $dados = PerdasApi::validar($request);
        $periodo = PerdasApi::periodoDe($dados);

        return Inertia::render('Relatorios/Perdas', [
            'relatorio' => $relatorio->resumo($periodo, $dados['tipo'] ?? null),
            'periodo' => [
                'periodo' => $periodo->chave(),
                'de' => $periodo->chave() === 'custom' ? $periodo->inicio()->toDateString() : null,
                'ate' => $periodo->chave() === 'custom' ? $periodo->fim()->toDateString() : null,
                'tipo' => $dados['tipo'] ?? null,
            ],
        ]);
    }
}
