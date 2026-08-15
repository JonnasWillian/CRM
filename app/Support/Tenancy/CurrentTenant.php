<?php

namespace App\Support\Tenancy;

use App\Models\Tenant;
use RuntimeException;

/**
 * Mantém o tenant ativo durante o ciclo de vida da requisição atual.
 *
 * Registrado como singleton no container (veja AppServiceProvider::register()),
 * portanto seu estado sobrevive durante toda a requisição.
 *
 * O método clear() existe por dois motivos:
 * - Testes: evita vazamento de tenant entre casos de teste, já que o container
 *   de testes pode reutilizar a mesma instância singleton entre asserts.
 * - Workers de fila de vida longa (futuro): um worker que processa múltiplos
 *   jobs sem reiniciar o processo precisa limpar o tenant entre jobs para não
 *   vazar o tenant de um job para o próximo.
 */
class CurrentTenant
{
    protected ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function get(): Tenant
    {
        if ($this->tenant === null) {
            throw new RuntimeException('Nenhum tenant ativo.');
        }

        return $this->tenant;
    }

    public function id(): int
    {
        return $this->get()->id;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
