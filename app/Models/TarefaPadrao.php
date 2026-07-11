<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TarefaPadrao extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tarefa_padroes';

    protected $fillable = ['user_id', 'titulo', 'anotacao', 'prazo_dias'];
}
