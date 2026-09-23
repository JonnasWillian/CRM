<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\BelongsToTenant;

class Usuario extends Model
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
}
