<?php

namespace App\Console\Commands;

use App\Services\Pagamentos\Asaas\ApiDoAsaas;
use App\Services\Pagamentos\Asaas\FalhaNoAsaas;
use Illuminate\Console\Command;

/**
 * Cadastra no Asaas o aviso de pagamento que aponta para este servidor.
 * Rodar uma vez por ambiente, depois de configurar ASAAS_WEBHOOK_TOKEN.
 * O endereço precisa ser público e com https: localhost não recebe avisos.
 */
class AsaasWebhook extends Command
{
    /** Só o que o sistema trata. */
    public const EVENTOS = ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED', 'PAYMENT_REFUNDED', 'PAYMENT_PARTIALLY_REFUNDED', 'PAYMENT_CHARGEBACK_REQUESTED', 'PAYMENT_DELETED'];

    protected $signature = 'asaas:webhook {--url= : endereço público do aviso (padrão: APP_URL + /api/webhooks/asaas)}';

    protected $description = 'Cadastra no Asaas o webhook de pagamentos deste servidor';

    public function handle(ApiDoAsaas $api): int
    {
        $token = (string) config('services.asaas.token_webhook');
        if (strlen($token) < 32 || strlen($token) > 255 || preg_match('/\s/', $token)) {
            $this->error('Configure ASAAS_WEBHOOK_TOKEN no .env: 32 a 255 caracteres, sem espaços. Gere com: php -r "echo bin2hex(random_bytes(32));"');

            return self::FAILURE;
        }

        $url = $this->option('url') ?: route('api.webhooks.asaas');
        if (! str_starts_with($url, 'https://')) {
            $this->error("O endereço precisa ser público e com https: {$url}. Ajuste APP_URL ou use --url.");

            return self::FAILURE;
        }

        try {
            $webhook = $api->criarWebhook([
                'name' => config('app.name').': pagamentos',
                'url' => $url,
                'email' => config('comercial.contato.email'),
                'enabled' => true,
                'interrupted' => false,
                'apiVersion' => 3,
                'authToken' => $token,
                // Em ordem: um estorno nunca chega antes da confirmação que ele desfaz.
                'sendType' => 'SEQUENTIALLY',
                'events' => self::EVENTOS,
            ]);
        } catch (FalhaNoAsaas $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Webhook cadastrado'.(isset($webhook['id']) ? " ({$webhook['id']})" : '').": {$url}");
        $this->line('Confira em Integrações > Webhooks no painel do Asaas.');

        return self::SUCCESS;
    }
}
