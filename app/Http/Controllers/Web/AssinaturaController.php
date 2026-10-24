<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Assinaturas\Assinaturas;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Configurações › Assinatura: situação, uso × limites e a escolha do plano. */
class AssinaturaController extends Controller
{
    public function index(Assinaturas $assinaturas, CurrentTenant $tenant): Response
    {
        return Inertia::render('Configuracoes/Assinatura', [
            'assinatura' => $assinaturas->resumo($tenant->get()),
            // Cada plano diz se o uso atual cabe nele (o teste não tem limite; o plano tem).
            'planos' => array_map(fn (array $p) => $p + ['excessos' => $assinaturas->excessos($tenant->get(), $p['chave'])], config('comercial.planos')),
            'diasDeTeste' => config('comercial.dias_de_teste'),
        ]);
    }

    public function escolher(Request $request, Assinaturas $assinaturas, CurrentTenant $tenant): RedirectResponse
    {
        $dados = $request->validate([
            'plano' => ['required', Rule::in(array_column(config('comercial.planos'), 'chave'))],
            'ciclo' => ['required', Rule::in(Assinaturas::CICLOS)],
        ], ['plano.in' => 'Escolha um dos planos disponíveis.']);

        if ($excessos = $assinaturas->excessos($tenant->get(), $dados['plano'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['plano' => implode(' ', $excessos)]);
        }

        $a = $assinaturas->ativar($tenant->get(), $dados['plano'], $dados['ciclo']);

        return redirect()->route('configuracoes.assinatura')->with('sucesso', "Plano {$a->planoConfig()['nome']} ativo até ".$a->vigente_ate->format('d/m/Y').'.');
    }
}
