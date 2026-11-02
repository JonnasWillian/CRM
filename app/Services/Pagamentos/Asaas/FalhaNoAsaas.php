<?php

namespace App\Services\Pagamentos\Asaas;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * Erro ao falar com o Asaas. `temporaria`: sem resposta, 429 ou 5xx, vale
 * tentar de novo (o webhook responde 503 para o Asaas reenviar). A mensagem
 * é para o log e para a equipe; `paraOCliente()` é o que a tela pode mostrar.
 */
class FalhaNoAsaas extends RuntimeException
{
    public function __construct(string $mensagem, public readonly ?int $status = null, public readonly bool $temporaria = false, ?Throwable $anterior = null)
    {
        parent::__construct($mensagem, 0, $anterior);
    }

    public static function semResposta(Throwable $e): self
    {
        return new self('O Asaas não respondeu: '.$e->getMessage(), null, true, $e);
    }

    public static function daResposta(Response $r): self
    {
        $status = $r->status();
        if ($status === 401) {
            return new self('O Asaas recusou a chave de API. Confira ASAAS_API_KEY e ASAAS_AMBIENTE (producao ou sandbox).', 401);
        }
        if ($status === 429 || $status >= 500) {
            return new self("O Asaas respondeu HTTP {$status}.", $status, true);
        }

        $descricoes = collect($r->json('errors') ?? [])->pluck('description')->filter()->implode(' ');

        return new self($descricoes !== '' ? $descricoes : "O Asaas recusou a operação (HTTP {$status}).", $status);
    }

    /** Erro de dados (400) é do cliente e pode ser mostrado; o resto é nosso e vira frase genérica. */
    public function paraOCliente(): string
    {
        if ($this->status === 400) {
            return $this->getMessage();
        }

        return $this->temporaria
            ? 'O sistema de pagamento não respondeu agora. Tente de novo em instantes.'
            : 'Não foi possível gerar a cobrança agora. Tente de novo em instantes ou fale com a gente.';
    }
}
