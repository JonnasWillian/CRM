<?php

namespace App\Services\Pagamentos\Asaas;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Chamadas à API v3 do Asaas (https://docs.asaas.com). A chave vai no header
 * `access_token`; contas criadas desde 13/06/2024 também exigem User-Agent.
 * POST nunca é repetido automaticamente: repetir criaria cobrança em dobro.
 */
class ApiDoAsaas
{
    public const URLS = [
        'producao' => 'https://api.asaas.com/v3',
        'sandbox' => 'https://api-sandbox.asaas.com/v3',
    ];

    public function ativo(): bool
    {
        return filled(config('services.asaas.chave'));
    }

    public function ambiente(): string
    {
        return config('services.asaas.ambiente') === 'producao' ? 'producao' : 'sandbox';
    }

    /** @return array<string, mixed> */
    public function criarCliente(array $dados): array
    {
        return $this->enviar(fn (PendingRequest $h) => $h->post('/customers', $dados));
    }

    /** @return array<string, mixed> */
    public function criarCobranca(array $dados): array
    {
        return $this->enviar(fn (PendingRequest $h) => $h->post('/payments', $dados));
    }

    /** @return array<string, mixed> */
    public function cobranca(string $id): array
    {
        return $this->enviar(fn (PendingRequest $h) => $h->get('/payments/'.rawurlencode($id)));
    }

    /** @return array{encodedImage?:string, payload?:string, expirationDate?:string} */
    public function qrCodePix(string $id): array
    {
        return $this->enviar(fn (PendingRequest $h) => $h->get('/payments/'.rawurlencode($id).'/pixQrCode'));
    }

    public function removerCobranca(string $id): void
    {
        $this->enviar(fn (PendingRequest $h) => $h->delete('/payments/'.rawurlencode($id)));
    }

    /** @return array<string, mixed> */
    public function criarWebhook(array $dados): array
    {
        return $this->enviar(fn (PendingRequest $h) => $h->post('/webhooks', $dados));
    }

    /** Leitura mínima, para conferir chave e ambiente. */
    public function testarConexao(): void
    {
        $this->enviar(fn (PendingRequest $h) => $h->get('/customers', ['limit' => 1]));
    }

    /** @return array<string, mixed> */
    private function enviar(callable $chamada): array
    {
        if (! $this->ativo()) {
            throw new FalhaNoAsaas('A integração com o Asaas não está configurada: falta ASAAS_API_KEY no .env.');
        }
        // Sandbox aceita cartão de teste: num servidor de produção, ativaria plano sem dinheiro.
        if (app()->isProduction() && $this->ambiente() !== 'producao') {
            throw new FalhaNoAsaas('Servidor de produção (APP_ENV=production) com ASAAS_AMBIENTE diferente de "producao". Corrija o .env.');
        }

        try {
            /** @var Response $resposta */
            $resposta = $chamada($this->cliente());
        } catch (ConnectionException $e) {
            throw FalhaNoAsaas::semResposta($e);
        }

        if ($resposta->failed()) {
            throw FalhaNoAsaas::daResposta($resposta);
        }

        return $resposta->json() ?? [];
    }

    private function cliente(): PendingRequest
    {
        return Http::baseUrl(self::URLS[$this->ambiente()])
            ->withHeaders([
                'access_token' => (string) config('services.asaas.chave'),
                'User-Agent' => config('app.name').'/1.0',
            ])
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(20);
    }
}
