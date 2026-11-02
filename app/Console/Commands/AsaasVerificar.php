<?php

namespace App\Console\Commands;

use App\Services\Pagamentos\Asaas\ApiDoAsaas;
use App\Services\Pagamentos\Asaas\FalhaNoAsaas;
use Illuminate\Console\Command;

/** Confere a configuração e a conexão com o Asaas, sem mostrar a chave. */
class AsaasVerificar extends Command
{
    protected $signature = 'asaas:verificar';

    protected $description = 'Confere se a chave e o ambiente do Asaas funcionam';

    public function handle(ApiDoAsaas $api): int
    {
        if (! $api->ativo()) {
            $this->error('Falta ASAAS_API_KEY no .env (entre aspas simples, a chave começa com $). Sem ela, as faturas são confirmadas à mão com fatura:pagar.');

            return self::FAILURE;
        }

        $ambiente = $api->ambiente() === 'producao' ? 'produção' : 'sandbox (testes)';
        try {
            $api->testarConexao();
        } catch (FalhaNoAsaas $e) {
            $this->error("Asaas, {$ambiente}: {$e->getMessage()}");

            return self::FAILURE;
        }
        $this->info("Conexão com o Asaas funcionando. Ambiente: {$ambiente}.");

        if (strlen((string) config('services.asaas.token_webhook')) < 32) {
            $this->warn('ASAAS_WEBHOOK_TOKEN não está configurado (32 a 255 caracteres). Sem ele, a confirmação depende de asaas:conciliar e da tela de pagamento.');
        }

        return self::SUCCESS;
    }
}
