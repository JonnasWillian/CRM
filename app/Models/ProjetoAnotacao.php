<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToTenant;

class ProjetoAnotacao extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'projetoAnotacaos';

    protected $fillable = [
        'descricao',
        'projeto_id',
    ];

    public function projeto()
    {
        return $this->belongsTo(Projeto::class, 'projeto_id');
    }
}
