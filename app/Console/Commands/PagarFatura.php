<?php

namespace App\Console\Commands;

use App\Models\Assinatura;
use App\Models\Fatura;
use App\Services\Assinaturas\Faturamento;
use DomainException;
use Illuminate\Console\Command;

/** Confirma o pagamento de uma fatura recebido fora do sistema (Pix, boleto, transferência). */
class PagarFatura extends Command
{
    protected $signature = 'fatura:pagar {fatura : id da fatura (veja faturas:pendentes)} {--referencia= : comprovante, id do Pix ou observação}';

    protected $description = 'Confirma o pagamento de uma fatura e ativa ou renova o plano';

    public function handle(Faturamento $faturamento): int
    {
        $fatura = Fatura::with('tenant')->find($this->argument('fatura'));
        if ($fatura === null) {
            $this->error('Fatura não encontrada.');

            return self::FAILURE;
        }
        if ($fatura->status === 'paga') {
            $this->warn("A fatura #{$fatura->id} já estava paga em {$fatura->paga_em->format('d/m/Y')}. Nada mudou.");

            return self::FAILURE;
        }

        try {
            $faturamento->pagar($fatura, $this->option('referencia'));
        } catch (DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $a = Assinatura::where('tenant_id', $fatura->tenant_id)->first();
        $this->info("Fatura #{$fatura->id} paga ({$fatura->tenant->nome}). Plano {$fatura->planoNome()} ({$fatura->ciclo}) vigente até {$a->vigente_ate->format('d/m/Y')}.");

        return self::SUCCESS;
    }
}
