<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class Statu extends Model
{
    protected $fillable = [
        'id',
        'descricao',
        'ordem'
    ];
}
