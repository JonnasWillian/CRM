<?php

namespace App\Services\Assinaturas;

use App\Models\Fatura;
use App\Models\Tenant;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Faturas: cotar, gerar e confirmar pagamento. Contratar gera fatura; só
 * pagar() ativa o plano. Hoje quem chama pagar() é o comando `fatura:pagar`;
 * com gateway, será o webhook — por isso pagar() é idempotente.
 */
class Faturamento
{
    public function __construct(private readonly Assinaturas $assinaturas) {}

    /**
     * Valor do ciclo para os usuários ativos de hoje (mínimo 1).
     *
     * @return array{usuarios:int, meses:int, preco_unitario:float, valor:float}
     */
    public function cotar(Tenant|int $tenant, string $plano, string $ciclo): array
    {
        $config = collect(config('comercial.planos', []))->firstWhere('chave', $plano)
            ?? throw new InvalidArgumentException("Plano desconhecido: {$plano}");
        if (! in_array($ciclo, Assinaturas::CICLOS, true)) {
            throw new InvalidArgumentException("Ciclo desconhecido: {$ciclo}");
        }

        $usuarios = max(1, $this->assinaturas->uso($tenant)['usuarios']);
        $meses = $ciclo === 'anual' ? 12 : 1;
        $preco = (float) ($ciclo === 'anual' ? $config['preco_anual'] : $config['preco_mensal']);

        return ['usuarios' => $usuarios, 'meses' => $meses, 'preco_unitario' => $preco, 'valor' => round($preco * $meses * $usuarios, 2)];
    }

    /** Gera a fatura e cancela a que estiver pendente (uma pendente por empresa). */
    public function gerar(Tenant|int $tenant, string $plano, string $ciclo, string $motivo, ?Carbon $venceEm = null): Fatura
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;
        $cotacao = $this->cotar($id, $plano, $ciclo);
        $venceEm ??= $this->vencimentoPadrao($id);

        return DB::transaction(function () use ($id, $plano, $ciclo, $motivo, $cotacao, $venceEm) {
            Fatura::where('tenant_id', $id)->where('status', 'pendente')->lockForUpdate()
                ->update(['status' => 'cancelada', 'cancelada_em' => Carbon::now()]);

            return Fatura::create($cotacao + [
                'tenant_id' => $id,
                'plano' => $plano,
                'ciclo' => $ciclo,
                'motivo' => $motivo,
                'status' => 'pendente',
                'vence_em' => $venceEm->toDateString(),
            ]);
        });
    }

    /**
     * Confirma o pagamento e ativa ou renova o plano da fatura. Chamar de
     * novo numa fatura paga não faz nada (o gateway reenvia avisos).
     *
     * @throws DomainException fatura cancelada
     */
    public function pagar(Fatura $fatura, ?string $referencia = null, string $gateway = 'manual'): Fatura
    {
        return DB::transaction(function () use ($fatura, $referencia, $gateway) {
            $f = Fatura::whereKey($fatura->id)->lockForUpdate()->firstOrFail();
            if ($f->status === 'paga') {
                return $f;
            }
            if ($f->status === 'cancelada') {
                throw new DomainException("A fatura #{$f->id} foi cancelada e não pode ser paga. Gere uma nova ou use assinatura:ativar.");
            }

            $this->assinaturas->ativar($f->tenant_id, $f->plano, $f->ciclo);
            $f->fill(['status' => 'paga', 'paga_em' => Carbon::now(), 'gateway' => $gateway, 'referencia_gateway' => $referencia])->save();

            return $f;
        });
    }

    public function pendente(Tenant|int $tenant): ?Fatura
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return Fatura::where('tenant_id', $id)->where('status', 'pendente')->latest('id')->first();
    }

    /** No teste, vence no fim do teste; fora dele, em N dias (comercial.prazo_de_pagamento_dias). */
    private function vencimentoPadrao(int $tenantId): Carbon
    {
        $prazo = Carbon::today()->addDays((int) config('comercial.prazo_de_pagamento_dias', 3));
        $a = $this->assinaturas->da($tenantId);

        return $a->situacao() === 'teste' && $a->teste_termina_em->copy()->startOfDay()->gt($prazo)
            ? $a->teste_termina_em->copy()->startOfDay()
            : $prazo;
    }
}
