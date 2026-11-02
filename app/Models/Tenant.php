<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nome',
        'slug',
        'plano',
    ];

    /** Respostas do questionário de primeiro acesso (só empresas cadastradas depois dele). */
    public function perfil(): HasOne
    {
        return $this->hasOne(PerfilDeEmpresa::class);
    }
}
