<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Perdas\Perdivel;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Projeto extends Model implements Perdivel
{
    use HasFactory, Notifiable, BelongsToTenant;

    protected $fillable = [
        'nome',
        'descricao',
        'preco',
        'data_inicial',
        'data_final',
        'parcelas',
        'qtd_parcelas',
        'usuario_id',
        'status_id',
    ];

    public function status()
    {
        return $this->belongsTo(Statu::class, 'status_id')->withTrashed();
    }

    public function perdas(): MorphMany
    {
        return $this->morphMany(Perda::class, 'perdivel');
    }

    /**
     * Para o projeto, "perdido" é `is_lost` do status — o eixo que já existia
     * antes dos funis e que não mudou. Só a exigência do motivo é nova.
     */
    public function estadoAtualEhPerda(): bool
    {
        return (bool) $this->status?->is_lost;
    }

    public function estadoSeriaPerda(array $atributos): bool
    {
        $statusId = array_key_exists('status_id', $atributos)
            ? $atributos['status_id']
            : $this->status_id;

        if ($statusId === null) {
            return false;
        }

        return (bool) Statu::withTrashed()->whereKey($statusId)->value('is_lost');
    }

    public function valorDaPerda(): ?float
    {
        return $this->preco !== null ? (float) $this->preco : null;
    }
}
