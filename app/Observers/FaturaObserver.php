<?php

namespace App\Observers;

use App\Jobs\RemoverCobrancaNoAsaas;
use App\Models\Fatura;
use App\Services\Pagamentos\Asaas\ApiDoAsaas;

class FaturaObserver
{
    /**
     * A cobrança aberta no Asaas deixa de valer quando a fatura é cancelada
     * ou é paga por outro caminho (à mão, ou por outra cobrança): remove lá,
     * para o cliente não pagar duas vezes.
     */
    public function updated(Fatura $fatura): void
    {
        if (! $fatura->wasChanged('status') || $fatura->cobranca_id === null || ! app(ApiDoAsaas::class)->ativo()) {
            return;
        }

        $pagaPorOutroCaminho = $fatura->status === 'paga' && $fatura->referencia_gateway !== $fatura->cobranca_id;
        if ($fatura->status === 'cancelada' || $pagaPorOutroCaminho) {
            RemoverCobrancaNoAsaas::dispatch($fatura->cobranca_id);
        }
    }
}
