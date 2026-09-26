<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Perdas\Perdivel;

class Usuario extends Model implements Perdivel
{
    use HasFactory, Notifiable, BelongsToTenant;

    protected $fillable = [
        'nome',
        'email',
        'descricao',
        'telefone',
        'user_id',
        'funil_id',
        'estagio_id',
    ];

    public function estagio()
    {
        return $this->belongsTo(Estagio::class, 'estagio_id')->withTrashed();
    }

    public function funil()
    {
        return $this->belongsTo(Funil::class, 'funil_id')->withTrashed();
    }

    public function perdas(): MorphMany
    {
        return $this->morphMany(Perda::class, 'perdivel');
    }

    /**
     * Para o lead, "perdido" é o tipo do estágio em que ele está.
     *
     * `estagio()` inclui arquivados de propósito: um estágio perdido que foi
     * arquivado enquanto ainda segurava leads continua sendo um estágio
     * perdido, e o lead que está nele continua perdido.
     */
    public function estadoAtualEhPerda(): bool
    {
        return $this->estagio?->tipo === Estagio::TIPO_PERDIDO;
    }

    public function estadoSeriaPerda(array $atributos): bool
    {
        $estagioId = array_key_exists('estagio_id', $atributos)
            ? $atributos['estagio_id']
            : $this->estagio_id;

        if ($estagioId === null) {
            return false;
        }

        // Passa pelo TenantScope: um id de outro tenant não resolve e a
        // resposta é "não é perda" — a validação do endpoint é quem recusa o
        // id inválido, não esta função.
        return Estagio::withTrashed()->whereKey($estagioId)->value('tipo') === Estagio::TIPO_PERDIDO;
    }

    /**
     * O que se perde quando um lead é perdido é o dinheiro que ainda estava em
     * jogo: a soma dos projetos dele que não foram nem ganhos nem perdidos.
     * Projeto já fechado não entra — ele não foi perdido junto.
     */
    public function valorDaPerda(): ?float
    {
        $soma = Projeto::where('usuario_id', $this->id)
            ->whereHas('status', fn ($q) => $q->open())
            ->sum('preco');

        return $soma > 0 ? (float) $soma : null;
    }
}
