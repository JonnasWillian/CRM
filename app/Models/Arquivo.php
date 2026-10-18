<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Arquivo extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'local',
        'nome',
        'usuario_id',
        'tamanho',
        'mime',
    ];

    /**
     * O caminho no disco é detalhe interno. O cliente recebe a URL que passa
     * pela policy (DownloadDeArquivoController), nunca o caminho.
     */
    protected $hidden = ['local'];

    protected $appends = ['url_download'];

    public function getUrlDownloadAttribute(): string
    {
        return route('arquivos.download', $this);
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
