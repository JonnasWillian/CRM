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

    protected $fillable = ['tenant_id', 'plano', 'ciclo', 'status', 'teste_termina_em', 'vigente_ate', 'ativada_em', 'cancelada_em', 'gateway', 'referencia_gateway'];

    protected function casts(): array
    {
        return ['teste_termina_em' => 'datetime', 'vigente_ate' => 'datetime', 'ativada_em' => 'datetime', 'cancelada_em' => 'datetime'];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /** teste · teste_expirado · ativa · vencida · cancelada — calculado na hora, sem cron. */
    public function situacao(): string
    {
        if ($this->status === 'cancelada') {
            return 'cancelada';
        }
        if ($this->status === 'ativa') {
            return $this->vigente_ate !== null && $this->vigente_ate->isFuture() ? 'ativa' : 'vencida';
        }

        return $this->teste_termina_em !== null && $this->teste_termina_em->isFuture() ? 'teste' : 'teste_expirado';
    }

    public function bloqueada(): bool
    {
        return in_array($this->situacao(), self::SITUACOES_BLOQUEADAS, true);
    }

    /** Dias até o fim do teste ou do ciclo pago (0 quando já passou). */
    public function diasRestantes(): int
    {
        $fim = match ($this->situacao()) {
            'teste' => $this->teste_termina_em,
            'ativa' => $this->vigente_ate,
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
