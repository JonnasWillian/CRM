<?php

namespace App\Support\Projetos;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * O contrato recorrente em meses: o período inicial e, em sequência, cada
 * renovação/aditivo, com o valor mensal de cada um (null = mantém o
 * anterior). Puro: sem banco, com "hoje" injetado.
 *
 * Regra de contagem (competência): o mês k começa no dia de aniversário do
 * início (15/08, 15/09…; dia 31 cai no último dia dos meses curtos) e conta
 * como realizado a partir desse dia — o mês corrente já conta. Um contrato
 * cancelado (projeto perdido) para de contar na data do cancelamento.
 *
 * Calculado, não recebido: não há registro de pagamento efetivo.
 */
final class CronogramaDeRecorrencia
{
    private CarbonImmutable $inicio;

    /** @var list<float> valor de cada mês, em ordem */
    private array $valores = [];

    /** @var list<array{meses:int, valor_mensal:float, inicio:string, fim:string}> */
    private array $periodos = [];

    private CarbonImmutable $hoje;

    private ?CarbonImmutable $cancelado;

    /**
     * @param  list<array{meses:int, valor_mensal:float|int|string|null}>  $periodos
     */
    public function __construct(CarbonInterface $inicio, array $periodos, CarbonInterface $hoje, ?CarbonInterface $cancelado = null)
    {
        $this->inicio = CarbonImmutable::instance($inicio)->startOfDay();
        $this->hoje = CarbonImmutable::instance($hoje)->startOfDay();
        $this->cancelado = $cancelado ? CarbonImmutable::instance($cancelado)->startOfDay() : null;

        $valor = 0.0;
        foreach ($periodos as $p) {
            $meses = max(0, (int) $p['meses']);
            if ($p['valor_mensal'] !== null && $p['valor_mensal'] !== '') {
                $valor = round((float) $p['valor_mensal'], 2);
            }
            $primeiro = count($this->valores);
            for ($i = 0; $i < $meses; $i++) {
                $this->valores[] = $valor;
            }
            if ($meses > 0) {
                $this->periodos[] = [
                    'meses' => $meses,
                    'valor_mensal' => $valor,
                    'inicio' => $this->inicioDoMes($primeiro)->toDateString(),
                    'fim' => $this->inicioDoMes($primeiro + $meses)->subDay()->toDateString(),
                ];
            }
        }
    }

    public function inicioDoMes(int $k): CarbonImmutable
    {
        return $this->inicio->addMonthsNoOverflow($k);
    }

    public function inicio(): CarbonImmutable
    {
        return $this->inicio;
    }

    public function fim(): CarbonImmutable
    {
        return $this->inicioDoMes($this->mesesTotal())->subDay();
    }

    public function mesesTotal(): int
    {
        return count($this->valores);
    }

    public function situacao(): string
    {
        if ($this->cancelado !== null) {
            return 'cancelado';
        }
        if ($this->hoje->lt($this->inicio)) {
            return 'a_iniciar';
        }

        return $this->hoje->gt($this->fim()) ? 'encerrado' : 'vigente';
    }

    /** Meses já iniciados até hoje (ou até o cancelamento). */
    public function mesesDecorridos(): int
    {
        $limite = $this->cancelado !== null && $this->cancelado->lt($this->hoje) ? $this->cancelado : $this->hoje;
        $n = 0;
        while ($n < $this->mesesTotal() && $this->inicioDoMes($n)->lte($limite)) {
            $n++;
        }

        return $n;
    }

    public function valorTotal(): float
    {
        return round(array_sum($this->valores), 2);
    }

    public function valorRealizado(): float
    {
        return round(array_sum(array_slice($this->valores, 0, $this->mesesDecorridos())), 2);
    }

    /** O que falta até o fim do contrato; zero se cancelado. */
    public function valorARealizar(): float
    {
        return $this->situacao() === 'cancelado' ? 0.0 : round($this->valorTotal() - $this->valorRealizado(), 2);
    }

    public function valorMesAtual(): float
    {
        return $this->situacao() === 'vigente' ? $this->valores[$this->mesesDecorridos() - 1] : 0.0;
    }

    /** Vigente e termina em até 31 dias: hora de renovar. */
    public function venceEmBreve(): bool
    {
        return $this->situacao() === 'vigente' && $this->hoje->diffInDays($this->fim()) <= 31;
    }

    /** @return list<array{meses:int, valor_mensal:float, inicio:string, fim:string}> */
    public function periodos(): array
    {
        return $this->periodos;
    }

    public function toArray(): array
    {
        return [
            'situacao' => $this->situacao(),
            'inicio' => $this->inicio->toDateString(),
            'fim' => $this->mesesTotal() > 0 ? $this->fim()->toDateString() : null,
            'meses_total' => $this->mesesTotal(),
            'meses_decorridos' => $this->mesesDecorridos(),
            'valor_total' => $this->valorTotal(),
            'valor_realizado' => $this->valorRealizado(),
            'valor_a_realizar' => $this->valorARealizar(),
            'valor_mes_atual' => $this->valorMesAtual(),
            'vence_em_breve' => $this->venceEmBreve(),
            'periodos' => $this->periodos,
        ];
    }
}
