<?php

namespace App\Services\Assinaturas;

use App\Models\Assinatura;
use App\Models\Fatura;
use App\Models\Tenant;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Faturas: simular, contratar, gerar e confirmar pagamento. Contratar gera
 * fatura; só pagar() ativa o plano. Hoje quem chama pagar() é o comando
 * `fatura:pagar`; com gateway, será o webhook — por isso pagar() é idempotente.
 *
 * Usuários são assentos: a fatura cobra N usuários e o plano pago passa a
 * cobrir N. Regras de troca (ver docs/comercial/plano-de-cobranca-e-assinaturas.md):
 * - teste, só leitura ou cancelada: contratação, sem crédito;
 * - plano pago, na janela de renovação (fatura de renovação pendente ou
 *   carência): a escolha substitui a renovação e continua do vencimento;
 * - plano pago, fora da janela: mesmo plano/ciclo/assentos = renovação
 *   antecipada; upgrade (mais caro por mês, ciclo igual ou mais longo) =
 *   troca no pagamento com crédito do que sobra; o resto espera a janela.
 */
class Faturamento
{
    public const MOTIVOS = ['contratacao', 'renovacao', 'troca', 'manual'];

    public function __construct(private readonly Assinaturas $assinaturas, private readonly AvisosDeAssinatura $avisos) {}

    /**
     * Valor bruto do ciclo para `$assentos` usuários (padrão: os ocupados
     * hoje), respeitando o mínimo do plano (`usuarios_minimos`, padrão 1).
     *
     * @return array{usuarios:int, meses:int, preco_unitario:float, valor:float}
     */
    public function cotar(Tenant|int $tenant, string $plano, string $ciclo, ?int $assentos = null): array
    {
        $config = $this->plano($plano);
        if (! in_array($ciclo, Assinaturas::CICLOS, true)) {
            throw new InvalidArgumentException("Ciclo desconhecido: {$ciclo}");
        }

        $usuarios = max((int) ($config['usuarios_minimos'] ?? 1), $assentos ?? $this->ocupados($tenant));
        $meses = $ciclo === 'anual' ? 12 : 1;
        $preco = (float) ($ciclo === 'anual' ? $config['preco_anual'] : $config['preco_mensal']);

        return ['usuarios' => $usuarios, 'meses' => $meses, 'preco_unitario' => $preco, 'valor' => round($preco * $meses * $usuarios, 2)];
    }

    /** Usuários ativos + convites pendentes: o mínimo de assentos que a empresa precisa. */
    public function ocupados(Tenant|int $tenant): int
    {
        return $this->assinaturas->uso($tenant)['usuarios'] + $this->assinaturas->convitesPendentes($tenant);
    }

    /**
     * O que contratar `$plano`/`$ciclo` com `$assentos` geraria, sem gravar.
     *
     * @return array{motivo:string, plano:string, ciclo:string, usuarios:int, bruto:float, credito:float, valor:float, vence_em:Carbon}
     *
     * @throws ValidationException quando não pode (assentos, limites ou troca fora da janela)
     */
    public function simular(Tenant|int $tenant, string $plano, string $ciclo, ?int $assentos = null): array
    {
        $config = $this->plano($plano);
        $a = $this->assinaturas->da($tenant);
        $situacao = $a->situacao();
        $emVigor = in_array($situacao, ['ativa', 'carencia'], true);
        $ocupados = $this->ocupados($tenant);

        // Primeiro o que a empresa precisa reduzir; depois a quantidade de assentos.
        if ($excessos = $this->assinaturas->excessos($tenant, $plano)) {
            throw ValidationException::withMessages(['plano' => implode(' ', $excessos)]);
        }

        $assentos ??= max($ocupados, $emVigor ? (int) $a->assentos : 0);
        if ($assentos < $ocupados) {
            throw ValidationException::withMessages(['assentos' => "A empresa tem {$ocupados} usuários entre ativos e convites pendentes. Escolha pelo menos {$ocupados}."]);
        }
        $limite = $config['limites']['usuarios'] ?? null;
        if ($limite !== null && $assentos > $limite) {
            throw ValidationException::withMessages(['assentos' => "O plano {$config['nome']} permite até {$limite} usuários."]);
        }

        $cotacao = $this->cotar($tenant, $plano, $ciclo, $assentos);
        $credito = 0.0;
        $pendente = $this->pendente($tenant);

        if (! $emVigor) {
            $motivo = 'contratacao';
            $venceEm = $this->vencimentoDaContratacao($a);
        } elseif ($situacao === 'carencia' || $pendente?->motivo === 'renovacao') {
            $motivo = 'renovacao'; // janela de renovação: a escolha vale para o próximo ciclo
            $venceEm = $this->vencimentoDaRenovacao($a);
        } elseif ($plano === $a->plano && $ciclo === ($a->ciclo ?? 'mensal') && $cotacao['usuarios'] === (int) ($a->assentos ?? $cotacao['usuarios'])) {
            $motivo = 'renovacao'; // renovação antecipada
            $venceEm = $this->vencimentoDaRenovacao($a);
        } elseif ($this->ehUpgrade($a, $plano, $ciclo, $cotacao['usuarios'], $ocupados)) {
            $motivo = 'troca';
            $credito = min($cotacao['valor'], $this->creditoRestante($a));
            $venceEm = Carbon::today()->addDays((int) config('comercial.prazo_de_pagamento_dias', 3));
        } else {
            $janela = $a->vigente_ate->copy()->subDays((int) config('comercial.renovacao_antecedencia_dias', 7));
            throw ValidationException::withMessages(['plano' => "Trocar para um plano menor, menos usuários ou um ciclo mais curto vale na renovação. A partir de {$janela->format('d/m/Y')}, escolha o novo plano aqui."]);
        }

        return [
            'motivo' => $motivo, 'plano' => $plano, 'ciclo' => $ciclo, 'usuarios' => $cotacao['usuarios'],
            'bruto' => $cotacao['valor'], 'credito' => round($credito, 2), 'valor' => round($cotacao['valor'] - $credito, 2), 'vence_em' => $venceEm,
        ];
    }

    /** Escolha do cliente na tela: simula e gera a fatura. */
    public function contratar(Tenant|int $tenant, string $plano, string $ciclo, ?int $assentos = null): Fatura
    {
        $s = $this->simular($tenant, $plano, $ciclo, $assentos);

        return $this->gerar($tenant, $plano, $ciclo, $s['motivo'], $s['vence_em'], $s['usuarios'], $s['credito']);
    }

    /** Gera a fatura e cancela a que estiver pendente (uma pendente por empresa). */
    public function gerar(Tenant|int $tenant, string $plano, string $ciclo, string $motivo, ?Carbon $venceEm = null, ?int $assentos = null, float $credito = 0): Fatura
    {
        if (! in_array($motivo, self::MOTIVOS, true)) {
            throw new InvalidArgumentException("Motivo desconhecido: {$motivo}");
        }
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $cotacao = $this->cotar($id, $plano, $ciclo, $assentos);
        $venceEm ??= $this->vencimentoDaContratacao($this->assinaturas->da($id));
        $credito = min($credito, $cotacao['valor']);

        $fatura = DB::transaction(function () use ($id, $plano, $ciclo, $motivo, $cotacao, $venceEm, $credito) {
            Fatura::where('tenant_id', $id)->where('status', 'pendente')->lockForUpdate()
                ->update(['status' => 'cancelada', 'cancelada_em' => Carbon::now()]);

            return Fatura::create([
                'tenant_id' => $id,
                'plano' => $plano,
                'ciclo' => $ciclo,
                'motivo' => $motivo,
                'usuarios' => $cotacao['usuarios'],
                'preco_unitario' => $cotacao['preco_unitario'],
                'meses' => $cotacao['meses'],
                'credito' => $credito,
                'valor' => round($cotacao['valor'] - $credito, 2),
                'status' => 'pendente',
                'vence_em' => $venceEm->toDateString(),
            ]);
        });

        // Ativação manual (assinatura:ativar) já nasce paga: não há o que cobrar.
        if ($motivo !== 'manual') {
            $this->avisos->faturaGerada($fatura);
        }

        return $fatura;
    }

    /**
     * Confirma o pagamento e ativa ou renova o plano da fatura. Chamar de
     * novo numa fatura paga não faz nada (o gateway reenvia avisos).
     *
     * @throws DomainException fatura cancelada
     */
    public function pagar(Fatura $fatura, ?string $referencia = null, string $gateway = 'manual'): Fatura
    {
        $pagaAgora = false;
        $f = DB::transaction(function () use ($fatura, $referencia, $gateway, &$pagaAgora) {
            $f = Fatura::whereKey($fatura->id)->lockForUpdate()->firstOrFail();
            if ($f->status === 'paga') {
                return $f;
            }
            if ($f->status === 'cancelada') {
                throw new DomainException("A fatura #{$f->id} foi cancelada e não pode ser paga. Gere uma nova ou use assinatura:ativar.");
            }

            // Troca começa no pagamento (o crédito pagou o que sobrava); o resto segue as regras de ativar().
            $inicio = $f->motivo === 'troca' ? Carbon::now() : null;
            $this->assinaturas->ativar($f->tenant_id, $f->plano, $f->ciclo, $inicio, $f->usuarios, (float) $f->valor + (float) $f->credito);
            $f->fill(['status' => 'paga', 'paga_em' => Carbon::now(), 'gateway' => $gateway, 'referencia_gateway' => $referencia])->save();
            $pagaAgora = true;

            return $f;
        });

        if ($pagaAgora) {
            $this->avisos->pagamentoConfirmado($f);
        }

        return $f;
    }

    public function pendente(Tenant|int $tenant): ?Fatura
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return Fatura::where('tenant_id', $id)->where('status', 'pendente')->latest('id')->first();
    }

    /**
     * Fatura de renovação do ciclo, se estiver na hora (N dias antes do fim
     * ou na carência) e ainda não houver. Fatura de troca vencida sem
     * pagamento é cancelada para a renovação sair. Usada pela rotina diária
     * e ao retomar a renovação.
     */
    public function garantirRenovacao(Assinatura $a): ?Fatura
    {
        $situacao = $a->situacao();
        if (! in_array($situacao, ['ativa', 'carencia'], true) || $a->renovacao_cancelada_em !== null) {
            return null;
        }
        if ($situacao === 'ativa' && $a->diasRestantes() > (int) config('comercial.renovacao_antecedencia_dias', 7)) {
            return null;
        }

        $pendente = $this->pendente($a->tenant_id);
        if ($pendente !== null) {
            if ($pendente->motivo === 'renovacao' || ! $pendente->atrasada()) {
                return null; // já há renovação, ou a troca ainda pode ser paga
            }
            $pendente->forceFill(['status' => 'cancelada', 'cancelada_em' => Carbon::now()])->save();
        }

        return $this->gerar($a->tenant_id, $a->plano, $a->ciclo ?? 'mensal', 'renovacao', $this->vencimentoDaRenovacao($a), $this->ocupados($a->tenant_id));
    }

    /**
     * Quanto vale o que sobra do ciclo pago: valor do ciclo × fração do tempo
     * que falta. Zero fora de "ativa" ou sem os dados do ciclo (planos antigos).
     */
    public function creditoRestante(Assinatura $a): float
    {
        if ($a->situacao() !== 'ativa' || $a->valor_do_ciclo === null || $a->inicio_do_ciclo === null || $a->vigente_ate === null) {
            return 0.0;
        }
        $total = $a->vigente_ate->getTimestamp() - $a->inicio_do_ciclo->getTimestamp();
        if ($total <= 0) {
            return 0.0;
        }
        $fracao = min(1, max(0, ($a->vigente_ate->getTimestamp() - Carbon::now()->getTimestamp()) / $total));

        return round((float) $a->valor_do_ciclo * $fracao, 2);
    }

    /** Mais caro por mês (preço × assentos) e com ciclo igual ou mais longo que o atual. */
    private function ehUpgrade(Assinatura $a, string $plano, string $ciclo, int $assentos, int $ocupados): bool
    {
        $atual = collect(config('comercial.planos', []))->firstWhere('chave', $a->plano);
        if ($atual === null) {
            return true; // plano que saiu da configuração: qualquer escolha é melhor que ele
        }
        $cicloAtual = $a->ciclo ?? 'mensal';
        $porMes = fn (array $p, string $c, int $n) => (float) ($c === 'anual' ? $p['preco_anual'] : $p['preco_mensal']) * $n;
        $mesesNovo = $ciclo === 'anual' ? 12 : 1;
        $mesesAtual = $cicloAtual === 'anual' ? 12 : 1;

        return $mesesNovo >= $mesesAtual && $porMes($this->plano($plano), $ciclo, $assentos) > $porMes($atual, $cicloAtual, max(1, (int) ($a->assentos ?? $ocupados)));
    }

    /** No teste, vence no fim do teste (sem carência, depois disso bloqueia); fora dele, em N dias. */
    private function vencimentoDaContratacao(Assinatura $a): Carbon
    {
        if ($a->situacao() === 'teste') {
            return $a->teste_termina_em->copy()->startOfDay();
        }

        return Carbon::today()->addDays((int) config('comercial.prazo_de_pagamento_dias', 3));
    }

    /** Renovação vence no fim do ciclo; já vencido (carência), vence hoje. */
    private function vencimentoDaRenovacao(Assinatura $a): Carbon
    {
        return Carbon::today()->max($a->vigente_ate->copy()->startOfDay());
    }

    /** @return array<string, mixed> */
    private function plano(string $chave): array
    {
        return collect(config('comercial.planos', []))->firstWhere('chave', $chave)
            ?? throw new InvalidArgumentException("Plano desconhecido: {$chave}");
    }
}
