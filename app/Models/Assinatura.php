<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A assinatura de uma empresa. Sem TenantScope de propósito: o middleware
 * que bloqueia escrita roda antes de o tenant ser ativado e consulta por
 * tenant_id.
 */
class Assinatura extends Model
{
    public const SITUACOES_BLOQUEADAS = ['teste_expirado', 'vencida', 'cancelada'];

    protected $fillable = ['tenant_id', 'plano', 'ciclo', 'status', 'teste_termina_em', 'vigente_ate', 'ativada_em', 'cancelada_em', 'renovacao_cancelada_em', 'gateway', 'referencia_gateway'];

    protected function casts(): array
    {
        return ['teste_termina_em' => 'datetime', 'vigente_ate' => 'datetime', 'ativada_em' => 'datetime', 'cancelada_em' => 'datetime', 'renovacao_cancelada_em' => 'datetime'];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * teste · teste_expirado · ativa · carencia · vencida · cancelada —
     * calculado na hora, sem cron. Carência: plano pago vencido há menos de
     * `comercial.carencia_dias`; ainda grava. O teste não tem carência.
     */
    public function situacao(): string
    {
        if ($this->status === 'cancelada') {
            return 'cancelada';
        }
        if ($this->status === 'ativa') {
            if ($this->vigente_ate !== null && $this->vigente_ate->isFuture()) {
                return 'ativa';
            }
            // Quem cancelou a renovação não deve nada: sem carência, sem cobrança.
            if ($this->renovacao_cancelada_em !== null) {
                return 'cancelada';
            }

            return $this->fimDaCarencia()?->isFuture() ? 'carencia' : 'vencida';
        }

        return $this->teste_termina_em !== null && $this->teste_termina_em->isFuture() ? 'teste' : 'teste_expirado';
    }

    /** Não grava: teste encerrado, vencida (depois da carência) ou cancelada. */
    public function bloqueada(): bool
    {
        return in_array($this->situacao(), self::SITUACOES_BLOQUEADAS, true);
    }

    /** Fim da carência de um plano pago (vencimento + N dias). */
    public function fimDaCarencia(): ?Carbon
    {
        return $this->vigente_ate?->copy()->addDays((int) config('comercial.carencia_dias', 3));
    }

    /** Desde quando a empresa não grava; null enquanto grava. */
    public function somenteLeituraDesde(): ?Carbon
    {
        return match ($this->situacao()) {
            'teste_expirado' => $this->teste_termina_em->copy(),
            'vencida' => $this->fimDaCarencia(),
            'cancelada' => ($this->status === 'cancelada' ? $this->cancelada_em : $this->vigente_ate)?->copy() ?? Carbon::now(),
            default => null,
        };
    }

    /** Quando a leitura também é cortada: N dias depois do início do só leitura. */
    public function suspendeEm(): ?Carbon
    {
        return $this->somenteLeituraDesde()?->addDays((int) config('comercial.suspender_apos_dias', 30));
    }

    /** Acesso suspenso: só planos, faturas, exportação, perfil e sair. */
    public function suspensa(): bool
    {
        return $this->suspendeEm()?->isPast() ?? false;
    }

    /** Dias até o fim do teste, do ciclo pago ou da carência (0 quando já passou). */
    public function diasRestantes(): int
    {
        $fim = match ($this->situacao()) {
            'teste' => $this->teste_termina_em,
            'ativa' => $this->vigente_ate,
            'carencia' => $this->fimDaCarencia(),
            default => null,
        };

        return $fim === null ? 0 : max(0, (int) Carbon::now()->startOfDay()->diffInDays($fim->copy()->startOfDay(), false));
    }

    /** O plano da configuração (comercial.planos). */
    public function planoConfig(): ?array
    {
        foreach (config('comercial.planos', []) as $p) {
            if ($p['chave'] === $this->plano) {
                return $p;
            }
        }

        return null;
    }

    /** @return array{usuarios:?int, funis:?int, leads:?int} null = sem limite. No teste tudo é liberado. */
    public function limites(): array
    {
        $l = $this->situacao() === 'teste' ? [] : ($this->planoConfig()['limites'] ?? []);

        return ['usuarios' => $l['usuarios'] ?? null, 'funis' => $l['funis'] ?? null, 'leads' => $l['leads'] ?? null];
    }
}
