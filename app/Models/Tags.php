<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class Tags extends Model
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'id',
        'descricao',
        'ordem'
    ];
}
