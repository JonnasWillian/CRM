<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\BelongsToTenant;

class ProjetoAnexo extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'projetoAnexos';

    protected $fillable = [
        'nome',
        'local',
        'projeto_id',
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
        return route('projetoAnexos.download', $this);
    }

    public function projeto()
    {
        return $this->belongsTo(Projeto::class, 'projeto_id');
    }
}
