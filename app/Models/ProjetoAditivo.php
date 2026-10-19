<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Renovação ou aditivo de um contrato recorrente: N meses a mais, com novo valor mensal opcional. */
class ProjetoAditivo extends Model
{
    use BelongsToTenant;

    public const TIPOS = ['renovacao', 'aditivo'];

    protected $table = 'projeto_aditivos';

    protected $fillable = ['projeto_id', 'tipo', 'meses', 'valor_mensal', 'observacao', 'user_id'];

    protected function casts(): array
    {
        return ['meses' => 'integer', 'valor_mensal' => 'decimal:2'];
    }

    public function projeto()
    {
        return $this->belongsTo(Projeto::class);
    }

    public function autor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
