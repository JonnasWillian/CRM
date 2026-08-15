<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToTenant;

class TarefaPadrao extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'tarefa_padroes';

    protected $fillable = ['user_id', 'titulo', 'anotacao', 'prazo_dias'];
}
