<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Convite para entrar na equipe do tenant. Pendente = não aceito, não
 * cancelado e dentro do prazo.
 */
class Convite extends Model
{
    use BelongsToTenant, HasFactory;

    public const PAPEIS = ['admin', 'gestor', 'vendedor'];

    protected $fillable = ['email', 'papel', 'token_hash', 'convidado_por', 'expira_em'];

    protected function casts(): array
    {
        return [
            'expira_em' => 'datetime',
            'aceito_em' => 'datetime',
            'cancelado_em' => 'datetime',
        ];
    }

    public function convidadoPor()
    {
        return $this->belongsTo(User::class, 'convidado_por');
    }

    public function scopePendentes(Builder $query): Builder
    {
        return $query->whereNull('aceito_em')->whereNull('cancelado_em')->where('expira_em', '>', now());
    }

    public function estaPendente(): bool
    {
        return $this->aceito_em === null && $this->cancelado_em === null && $this->expira_em->isFuture();
    }
}
