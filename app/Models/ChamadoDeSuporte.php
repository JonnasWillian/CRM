<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Uma resposta do assistente de ajuda e feedback. O id é o protocolo mostrado à pessoa. */
class ChamadoDeSuporte extends Model
{
    use BelongsToTenant;

    protected $table = 'chamados_de_suporte';

    protected $fillable = ['tipo', 'area', 'impacto', 'mensagem', 'nota', 'pagina'];

    protected function casts(): array
    {
        return ['acionou_suporte' => 'boolean', 'nota' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
