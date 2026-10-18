<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatusProjeto extends Model
{
    /** A tabela mantém o nome original. */
    protected $table = 'status';

    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'descricao',
        'ordem',
    ];

    /**
     * Status "em aberto" (nem ganho, nem perdido). Reutilizado nos 3 pontos de
     * os controllers de leads, kanban e métricas que precisam filtrar
     * projetos por status ainda ativo.
     */
    public function scopeOpen($query)
    {
        return $query->where('is_won', false)->where('is_lost', false);
    }
}
