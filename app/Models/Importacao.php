<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Uma importação de leads por CSV. */
class Importacao extends Model
{
    use BelongsToTenant, HasFactory;

    public const AGUARDANDO_MAPEAMENTO = 'aguardando_mapeamento';

    public const NA_FILA = 'na_fila';

    public const PROCESSANDO = 'processando';

    public const CONCLUIDA = 'concluida';

    public const FALHOU = 'falhou';

    protected $table = 'importacoes';

    protected $fillable = [
        'user_id', 'arquivo', 'nome_original', 'delimitador', 'codificacao', 'cabecalhos', 'amostra',
        'mapeamento', 'opcoes', 'status', 'total_linhas', 'processadas', 'criadas', 'atualizadas',
        'ignoradas', 'com_erro', 'erros', 'mensagem_falha', 'iniciada_em', 'concluida_em',
    ];

    protected function casts(): array
    {
        return [
            'cabecalhos' => 'array',
            'amostra' => 'array',
            'mapeamento' => 'array',
            'opcoes' => 'array',
            'erros' => 'array',
            'iniciada_em' => 'datetime',
            'concluida_em' => 'datetime',
        ];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
