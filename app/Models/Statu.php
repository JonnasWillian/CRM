<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\BelongsToTenant;

class Statu extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $fillable = [
        'id',
        'descricao',
        'ordem',
    ];

    /**
     * Status "em aberto" (nem ganho, nem perdido). Reutilizado nos 3 pontos de
     * Userarios.php (view(), kanban(), metricas()) que precisam filtrar
     * projetos por status ainda ativo.
     */
    public function scopeOpen($query)
    {
        return $query->where('is_won', false)->where('is_lost', false);
    }
}
