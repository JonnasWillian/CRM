<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class arquivo extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'local',
        'nome',
        'usuario_id',
        'updated_at',
        'deleted_at'
    ];
}
