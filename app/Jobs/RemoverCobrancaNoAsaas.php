<?php

namespace App\Jobs;

use App\Services\Pagamentos\Asaas\ApiDoAsaas;
use App\Services\Pagamentos\Asaas\FalhaNoAsaas;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Fatura cancelada aqui: a cobrança some do Asaas, para o cliente não pagar
 * uma fatura que não vale mais. Fila, depois do commit: a chamada externa
 * não segura a transação de quem cancelou.
 */
class RemoverCobrancaNoAsaas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly string $cobrancaId)
    {
        $this->afterCommit();
    }

    public function handle(ApiDoAsaas $api): void
    {
        if (! $api->ativo()) {
            return;
        }

        try {
            $api->removerCobranca($this->cobrancaId);
        } catch (FalhaNoAsaas $e) {
            if ($e->temporaria) {
                throw $e; // a fila tenta de novo
            }
            // Já paga ou já removida: o webhook avisa se o cliente pagou a fatura cancelada.
            Log::warning("Asaas: cobrança {$this->cobrancaId} não foi removida.", ['erro' => $e->getMessage()]);
        }
    }
}
