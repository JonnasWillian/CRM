<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UsuarioTagHistorico extends Model
{
    use HasFactory;

    protected $table = 'usuario_tag_historicos';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'usuario_id',
        'tag_id_anterior',
        'tag_id_novo',
    ];

    public function tagAnterior()
    {
        return $this->belongsTo(Tags::class, 'tag_id_anterior', 'id');
    }

    public function tagNovo()
    {
        return $this->belongsTo(Tags::class, 'tag_id_novo', 'id');
    }
}
