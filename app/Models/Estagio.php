<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\BelongsToTenant;

/**
 * Estágio de um funil (antes: Tags, quando o conjunto era fixo).
 *
 * `tipo` é a única coisa que o resto do sistema pode olhar para saber o que um
 * estágio significa. Com nomes livres por tenant, "Fechado", "Assinado" e
 * "Ganhamos" são o mesmo conceito e nenhum deles é reconhecível por string —
 * métricas, filtros e relatórios leem o tipo, nunca a descrição.
 *
 * `$table` é declarada explicitamente porque a pluralização automática do
 * Laravel é feita em inglês: ela acerta "estagios" por acidente, mas nada
 * garante isso para nomes em português.
 */
class Estagio extends Model
{
    use HasFactory, Notifiable, SoftDeletes, BelongsToTenant;

    public const TIPO_ABERTO = 'aberto';
    public const TIPO_GANHO = 'ganho';
    public const TIPO_PERDIDO = 'perdido';

    public const TIPOS = [
        self::TIPO_ABERTO,
        self::TIPO_GANHO,
        self::TIPO_PERDIDO,
    ];

    protected $table = 'estagios';

    /**
     * `id` saiu de $fillable junto com a chegada do CRUD: com um endpoint de
     * criação/edição aberto ao tenant, mantê-lo preenchível deixaria o cliente
     * escolher a própria chave primária.
     */
    protected $fillable = [
        'funil_id',
        'descricao',
        'ordem',
        'tipo',
        'cor',
    ];

    public function funil()
    {
        return $this->belongsTo(Funil::class)->withTrashed();
    }

    public function scopeAberto($query)
    {
        return $query->where('tipo', self::TIPO_ABERTO);
    }

    /**
     * Ganho ou perdido — o negócio saiu do pipeline, em qualquer direção.
     * É o que o antigo `is_active = false` tentava dizer.
     */
    public function scopeFechado($query)
    {
        return $query->whereIn('tipo', [self::TIPO_GANHO, self::TIPO_PERDIDO]);
    }
}
