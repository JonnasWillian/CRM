<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class Usuario extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'nome',
        'email',
        'descricao',
        'telefone',
        'user_id',
        'tag_id',
        'tenant_id'
    ];

    public function tag()
    {
        return $this->belongsTo(Tags::class, 'tag_id')->withTrashed();
    }
}
