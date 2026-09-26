<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Um evento de perda: o que se perdeu, por quê, quanto valia, quem registrou.
 *
 * Append-only. Reabrir um lead não apaga a perda dele — o negócio foi perdido
 * naquela data e o relatório daquele mês continua contando. Por isso não há
 * `updated_at`: não existe "editar uma perda", existe registrar outra.
 */
class Perda extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'perdas';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'motivo_perda_id',
        'observacao',
        'valor',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function perdivel()
    {
        return $this->morphTo();
    }

    public function motivo()
    {
        return $this->belongsTo(MotivoPerda::class, 'motivo_perda_id')->withTrashed();
    }

    public function registradoPor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeNoPeriodo($query, $inicio, $fim)
    {
        return $query->whereBetween('created_at', [$inicio, $fim]);
    }

    public function scopeDeLeads($query)
    {
        return $query->where('perdivel_type', Usuario::class);
    }

    public function scopeDeProjetos($query)
    {
        return $query->where('perdivel_type', Projeto::class);
    }
}
