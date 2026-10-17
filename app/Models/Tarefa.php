<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tarefa extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'tarefas';

    protected $fillable = [
        'usuario_id',
        'titulo',
        'anotacao',
        'data_limite',
        'concluido',
        'concluido_em',
    ];

    protected $casts = [
        'data_limite' => 'date',
        'concluido' => 'boolean',
        'concluido_em' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
