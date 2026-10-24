<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Cobrança de um ciclo de assinatura. Sem TenantScope pelo mesmo motivo de
 * Assinatura: a rotina diária e os comandos da equipe comercial percorrem
 * todas as empresas. Toda consulta filtra tenant_id explicitamente.
 */
class Fatura extends Model
{
    protected $table = 'faturas';

    protected $fillable = ['tenant_id', 'plano', 'ciclo', 'motivo', 'usuarios', 'preco_unitario', 'meses', 'credito', 'valor', 'status', 'vence_em', 'paga_em', 'cancelada_em', 'gateway', 'referencia_gateway'];

    protected function casts(): array
    {
        return [
            'usuarios' => 'integer',
            'meses' => 'integer',
            'preco_unitario' => 'decimal:2',
            'credito' => 'decimal:2',
            'valor' => 'decimal:2',
            'vence_em' => 'date',
            'paga_em' => 'datetime',
            'cancelada_em' => 'datetime',
        ];
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function planoNome(): string
    {
        return collect(config('comercial.planos', []))->firstWhere('chave', $this->plano)['nome'] ?? $this->plano;
    }

    public function atrasada(): bool
    {
        return $this->status === 'pendente' && $this->vence_em->lt(Carbon::today());
    }

    public static function emReais(float|string $valor): string
    {
        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }

    /** @return array<string, mixed> */
    public function paraOFront(): array
    {
        return [
            'id' => $this->id,
            'plano' => $this->plano,
            'plano_nome' => $this->planoNome(),
            'ciclo' => $this->ciclo,
            'motivo' => $this->motivo,
            'usuarios' => $this->usuarios,
            'preco_unitario' => (float) $this->preco_unitario,
            'meses' => $this->meses,
            'credito' => (float) $this->credito,
            'valor' => (float) $this->valor,
            'status' => $this->status,
            'atrasada' => $this->atrasada(),
            'vence_em' => $this->vence_em->toDateString(),
            'paga_em' => $this->paga_em?->toDateString(),
        ];
    }
}
