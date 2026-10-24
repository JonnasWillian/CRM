<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Fatura;
use App\Services\Assinaturas\Assinaturas;
use App\Services\Assinaturas\Faturamento;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Configurações › Assinatura: situação, uso × limites, faturas e a contratação do plano. */
class AssinaturaController extends Controller
{
    public function index(Assinaturas $assinaturas, Faturamento $faturamento, CurrentTenant $tenant): Response
    {
        $empresa = $tenant->get();

        return Inertia::render('Configuracoes/Assinatura', [
            'assinatura' => $assinaturas->resumo($empresa),
            // Cada plano diz se o uso atual cabe nele (o teste não tem limite;
            // o plano tem) e quanto custaria hoje, por ciclo.
            'planos' => array_map(fn (array $p) => $p + [
                'excessos' => $assinaturas->excessos($empresa, $p['chave']),
                'valor' => [
                    'mensal' => $faturamento->cotar($empresa, $p['chave'], 'mensal')['valor'],
                    'anual' => $faturamento->cotar($empresa, $p['chave'], 'anual')['valor'],
                ],
            ], config('comercial.planos')),
            'usuariosCobrados' => $faturamento->cotar($empresa, config('comercial.planos.0.chave'), 'mensal')['usuarios'],
            'faturaPendente' => $faturamento->pendente($empresa)?->paraOFront(),
            'faturas' => Fatura::where('tenant_id', $empresa->id)->latest('id')->limit(12)->get()->map->paraOFront()->values(),
            'pagamento' => [
                'instrucoes' => config('comercial.pagamento.instrucoes'),
                'email' => config('comercial.contato.email'),
                'whatsapp' => config('comercial.contato.whatsapp'),
            ],
            'diasDeTeste' => config('comercial.dias_de_teste'),
        ]);
    }

    public function escolher(Request $request, Assinaturas $assinaturas, Faturamento $faturamento, CurrentTenant $tenant): RedirectResponse
    {
        $dados = $request->validate([
            'plano' => ['required', Rule::in(array_column(config('comercial.planos'), 'chave'))],
            'ciclo' => ['required', Rule::in(Assinaturas::CICLOS)],
        ], ['plano.in' => 'Escolha um dos planos disponíveis.']);

        if ($excessos = $assinaturas->excessos($tenant->get(), $dados['plano'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['plano' => implode(' ', $excessos)]);
        }

        // Contratar não ativa: gera a fatura. Só o pagamento confirmado ativa.
        $fatura = $faturamento->gerar($tenant->get(), $dados['plano'], $dados['ciclo'], 'contratacao');

        return redirect()->route('configuracoes.assinatura')->with('sucesso', 'Fatura de '.Fatura::emReais($fatura->valor)." gerada. O plano {$fatura->planoNome()} é ativado assim que o pagamento for confirmado.");
    }
}
