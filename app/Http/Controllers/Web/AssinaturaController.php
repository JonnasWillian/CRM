<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Fatura;
use App\Rules\CpfOuCnpj;
use App\Services\Assinaturas\Assinaturas;
use App\Services\Assinaturas\Faturamento;
use App\Services\Pagamentos\Asaas\CobrancasDoAsaas;
use App\Services\Pagamentos\Asaas\FalhaNoAsaas;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Configurações › Assinatura: situação, uso × limites, faturas e a contratação do plano. */
class AssinaturaController extends Controller
{
    public function index(Assinaturas $assinaturas, Faturamento $faturamento, CobrancasDoAsaas $cobrancas, CurrentTenant $tenant): Response
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
            // Do questionário de primeiro acesso: quantas pessoas vão usar (null sem resposta).
            'usuariosSugeridos' => \App\Services\Onboarding\PreferenciasDaEmpresa::usuariosSugeridos($empresa->id),
            'faturaPendente' => $faturamento->pendente($empresa)?->paraOFront(),
            'faturas' => Fatura::where('tenant_id', $empresa->id)->latest('id')->limit(12)->get()->map->paraOFront()->values(),
            'pagamento' => [
                // Com o Asaas ligado, a fatura é paga na própria tela; sem ele, valem as instruções.
                'online' => $cobrancas->ativo(),
                'documento' => filled($empresa->documento),
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

    /**
     * Pix da fatura pendente (e o link da página do Asaas, para boleto e
     * cartão). Na primeira vez, pede o CPF/CNPJ de quem paga: o Asaas exige.
     */
    public function pagar(Request $request, Fatura $fatura, CobrancasDoAsaas $cobrancas, CurrentTenant $tenant): JsonResponse
    {
        $this->authorize('pagar', $fatura); // de outra empresa: 404
        abort_unless($cobrancas->ativo(), 404);
        $empresa = $tenant->get();

        if (blank($empresa->documento)) {
            $dados = $request->validate(
                ['documento' => ['required', 'string', 'max:20', new CpfOuCnpj]],
                ['documento.required' => 'Informe o CPF ou CNPJ de quem paga a fatura.'],
            );
            $empresa->forceFill(['documento' => CpfOuCnpj::digitos($dados['documento'])])->save();
        }

        if ($fatura->status !== 'pendente') {
            return response()->json(['message' => 'Esta fatura não está mais aberta para pagamento.'], 422);
        }

        try {
            $f = $cobrancas->garantir($fatura, $request->user()->email);
            $pix = $cobrancas->pix($f);
        } catch (FalhaNoAsaas $e) {
            Log::warning("Asaas: cobrança da fatura #{$fatura->id} não gerada.", ['erro' => $e->getMessage()]);

            return response()->json(['message' => $e->paraOCliente()], $e->temporaria ? 503 : 422);
        }

        return response()->json(['link' => $f->link_pagamento, 'pix' => $pix]);
    }

    /**
     * Situação da fatura, para a tela saber quando o pagamento caiu. Além do
     * webhook, confere no Asaas no máximo a cada 15 segundos por fatura:
     * cobre aviso atrasado e o ambiente local, que o webhook não alcança.
     */
    public function fatura(Fatura $fatura, CobrancasDoAsaas $cobrancas): JsonResponse
    {
        $this->authorize('pagar', $fatura); // de outra empresa: 404
        // Quando o Asaas respondeu pela última vez: a tela mostra que está conferindo de verdade.
        $conferida = "asaas:conferida:{$fatura->id}";
        if ($fatura->status === 'pendente' && $fatura->cobranca_id !== null && $cobrancas->ativo() && Cache::add("asaas:consulta:{$fatura->id}", true, 15)) {
            try {
                $cobrancas->confirmarSePaga($fatura);
                $fatura->refresh();
                Cache::put($conferida, Carbon::now()->toIso8601String(), 3600);
            } catch (FalhaNoAsaas $e) {
                Log::info("Asaas: consulta da fatura #{$fatura->id} falhou; a próxima tenta de novo.", ['erro' => $e->getMessage()]);
            }
        }

        return response()->json(['status' => $fatura->status, 'conferido_em' => Cache::get($conferida)]);
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
