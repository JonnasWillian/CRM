<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Motivo de perda do catálogo do tenant.
 *
 * Arquivar é soft delete: o motivo some do seletor de quem registra uma perda
 * nova, e continua resolvendo o nome das perdas que já o citam. Por isso a FK
 * de `perdas.motivo_perda_id` é restrict — remover fisicamente reescreveria a
 * história do relatório.
 */
class MotivoPerda extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'motivos_perda';

    protected $fillable = [
        'descricao',
        'ordem',
    ];

    public function perdas()
    {
        return $this->hasMany(Perda::class, 'motivo_perda_id');
    }

    /**
     * `ordem` é definida pelo usuário e pode empatar; o id desempata para que a
     * lista não mude de ordem entre dois requests.
     */
    public function scopeOrdenados($query)
    {
        return $query->orderBy('ordem')->orderBy('id');
    }
}
