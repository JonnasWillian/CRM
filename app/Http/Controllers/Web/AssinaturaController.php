<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Fatura;
use App\Services\Assinaturas\Assinaturas;
use App\Services\Assinaturas\Faturamento;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
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
                'usuarios' => $faturamento->cotar($empresa, $p['chave'], 'mensal')['usuarios'], // cobrados neste plano
                'valor' => [
                    'mensal' => $faturamento->cotar($empresa, $p['chave'], 'mensal')['valor'],
                    'anual' => $faturamento->cotar($empresa, $p['chave'], 'anual')['valor'],
                ],
            ], config('comercial.planos')),
            'usuariosCobrados' => $faturamento->cotar($empresa, config('comercial.planos.0.chave'), 'mensal')['usuarios'],
            // Mínimo de assentos que a empresa pode escolher: ativos + convites pendentes.
            'ocupados' => $faturamento->ocupados($empresa),
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

    /** Acesso suspenso: o que houve e o caminho para cada papel. Fora da suspensão, volta ao dashboard. */
    public function suspensa(Request $request, Assinaturas $assinaturas, CurrentTenant $tenant): Response|RedirectResponse
    {
        $resumo = $assinaturas->resumo($tenant->get());
        if (! $resumo['suspensa']) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Assinatura/Suspensa', [
            'assinatura' => $resumo,
            'empresa' => $tenant->get()->nome,
            'podeGerenciar' => $request->user()->can('configuracoes.manage'),
            'contato' => ['email' => config('comercial.contato.email'), 'whatsapp' => config('comercial.contato.whatsapp')],
        ]);
    }

    public function escolher(Request $request, Faturamento $faturamento, CurrentTenant $tenant): RedirectResponse
    {
        $dados = $this->validarEscolha($request);

        // Contratar não ativa: gera a fatura. Só o pagamento confirmado ativa.
        $fatura = $faturamento->contratar($tenant->get(), $dados['plano'], $dados['ciclo'], $dados['assentos'] ?? null);
        $valor = Fatura::emReais($fatura->valor);
        $mensagem = match ($fatura->motivo) {
            'troca' => "Fatura de {$valor} gerada, já com ".Fatura::emReais($fatura->credito)." de crédito do plano atual. A troca para o plano {$fatura->planoNome()} vale assim que o pagamento for confirmado.",
            'renovacao' => "Fatura de {$valor} gerada. O plano {$fatura->planoNome()} é renovado assim que o pagamento for confirmado.",
            default => "Fatura de {$valor} gerada. O plano {$fatura->planoNome()} é ativado assim que o pagamento for confirmado.",
        };

        return redirect()->route('configuracoes.assinatura')->with('sucesso', $mensagem);
    }

    /** O que a escolha geraria (tipo, crédito, valor), para a tela confirmar com os números do servidor. */
    public function previa(Request $request, Faturamento $faturamento, CurrentTenant $tenant): JsonResponse
    {
        $dados = $this->validarEscolha($request);
        $s = $faturamento->simular($tenant->get(), $dados['plano'], $dados['ciclo'], $dados['assentos'] ?? null);

        return response()->json(['vence_em' => $s['vence_em']->toDateString()] + $s);
    }

    /** @return array{plano:string, ciclo:string, assentos:?int} */
    private function validarEscolha(Request $request): array
    {
        return $request->validate([
            'plano' => ['required', Rule::in(array_column(config('comercial.planos'), 'chave'))],
            'ciclo' => ['required', Rule::in(Assinaturas::CICLOS)],
            'assentos' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ], ['plano.in' => 'Escolha um dos planos disponíveis.', 'assentos.integer' => 'Informe o número de usuários.']);
    }

    public function cancelarRenovacao(Assinaturas $assinaturas, CurrentTenant $tenant): RedirectResponse
    {
        try {
            $a = $assinaturas->cancelarRenovacao($tenant->get());
        } catch (\DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect()->route('configuracoes.assinatura')->with('sucesso', "Renovação cancelada. O plano {$a->planoConfig()['nome']} continua ativo até {$a->vigente_ate->format('d/m/Y')}.");
    }

    public function retomarRenovacao(Assinaturas $assinaturas, Faturamento $faturamento, CurrentTenant $tenant): RedirectResponse
    {
        try {
            $a = $assinaturas->retomarRenovacao($tenant->get());
        } catch (\DomainException $e) {
            return back()->with('erro', $e->getMessage());
        }

        // Dentro da janela de renovação, a fatura sai agora (a anterior foi cancelada junto com a renovação).
        $fatura = $faturamento->garantirRenovacao($a);
        $mensagem = $fatura !== null
            ? 'Renovação retomada. Fatura de '.Fatura::emReais($fatura->valor)." gerada, vence em {$fatura->vence_em->format('d/m/Y')}."
            : 'Renovação retomada. A fatura do próximo ciclo é gerada '.config('comercial.renovacao_antecedencia_dias', 7)." dias antes de {$a->vigente_ate->format('d/m/Y')}.";

        return redirect()->route('configuracoes.assinatura')->with('sucesso', $mensagem);
    }
}
