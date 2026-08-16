<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\BelongsToTenant;

class Projeto extends Model
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
}
