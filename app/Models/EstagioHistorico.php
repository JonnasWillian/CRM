<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Trilha de mudança de estágio de um lead (antes: UsuarioTagHistorico).
 *
 * Gravado exclusivamente pelo UsuarioObserver.
 */
class EstagioHistorico extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'estagio_historicos';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'usuario_id',
        'estagio_anterior_id',
        'estagio_novo_id',
        'funil_anterior_id',
        'funil_novo_id',
    ];

    public function estagioAnterior()
    {
        return $this->belongsTo(Estagio::class, 'estagio_anterior_id', 'id');
    }

    public function estagioNovo()
    {
        return $this->belongsTo(Estagio::class, 'estagio_novo_id', 'id');
    }

    public function funilAnterior()
    {
        return $this->belongsTo(Funil::class, 'funil_anterior_id', 'id');
    }

    public function funilNovo()
    {
        return $this->belongsTo(Funil::class, 'funil_novo_id', 'id');
    }
}
