<?php

namespace App\Support\Reports;

use Illuminate\Support\Facades\Cache;

/**
 * Chaves e invalidação do cache de métricas.
 *
 * A invalidação é por VERSÃO: cada escrita em lead ou projeto incrementa um
 * contador por tenant, e a versão entra na chave. Não há varredura de chaves
 * (o store `database` não lista por prefixo) e o TTL (config reports.metricas_ttl)
 * continua como rede de segurança.
 *
 * get + forever, e não Cache::increment: no store de banco, increment em
 * chave inexistente devolve false em vez de criar.
 */
final class MetricasCache
{
    public static function chave(int $tenantId, ?int $agenteId, ?int $funilId, string $hash): string
    {
        return sprintf(
            'tenant:%d:metricas:v%d:%s:%s:%s',
            $tenantId,
            self::versao($tenantId),
            $agenteId === null ? 'todos' : (string) $agenteId,
            $funilId === null ? 'todos' : (string) $funilId,
            $hash,
        );
    }

    public static function versao(int $tenantId): int
    {
        return (int) Cache::get(self::chaveVersao($tenantId), 1);
    }

    /** @var array<int, true>|null tenants pendentes enquanto um lote está aberto */
    private static ?array $lote = null;

    /**
     * Agrupa as invalidações de uma operação em massa: os observers chamam
     * invalidar() por lead, e o contador só sobe uma vez no fim.
     */
    public static function emLote(callable $operacao): mixed
    {
        if (self::$lote !== null) {
            return $operacao();
        }

        self::$lote = [];
        try {
            return $operacao();
        } finally {
            $pendentes = array_keys(self::$lote);
            self::$lote = null;
            foreach ($pendentes as $tenantId) {
                self::invalidar($tenantId);
            }
        }
    }

    public static function invalidar(?int $tenantId): void
    {
        if ($tenantId === null) {
            return;
        }

        if (self::$lote !== null) {
            self::$lote[$tenantId] = true;

            return;
        }

        Cache::forever(self::chaveVersao($tenantId), self::versao($tenantId) + 1);
    }

    private static function chaveVersao(int $tenantId): string
    {
        return "tenant:{$tenantId}:metricas:versao";
    }
}
