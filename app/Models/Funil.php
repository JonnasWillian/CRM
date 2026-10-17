<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Um funil de leads do tenant — "Vendas", "Pós-venda", o que a empresa quiser.
 *
 * `is_default` fica fora de $fillable de propósito: marcar um funil como padrão
 * implica desmarcar o anterior, e isso é uma operação de duas linhas que só o
 * FunilService faz, dentro de uma transação. Deixá-lo preenchível por mass
 * assignment permitiria criar dois padrões pelo formulário e deixar o tenant
 * com um estado que nenhuma tela sabe representar.
 */
class Funil extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'funis';

    protected $fillable = [
        'nome',
        'descricao',
        'ordem',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function estagios()
    {
        return $this->hasMany(Estagio::class)->orderBy('ordem');
    }

    public function leads()
    {
        return $this->hasMany(Usuario::class);
    }

    /**
     * `ordem` é definida pelo usuário e pode empatar entre dois funis; o id
     * desempata para que a listagem não mude de ordem entre dois requests.
     */
    /**
     * Funil em que o quadro e o cadastro operam quando o cliente não escolhe
     * um: o marcado como padrão; na falta dele, o primeiro da ordem — um
     * Kanban vazio sem explicação é pior que um Kanban no funil errado.
     */
    public static function padrao(): ?self
    {
        return static::where('is_default', true)->first() ?? static::ordenados()->first();
    }

    public function scopeOrdenados($query)
    {
        return $query->orderBy('ordem')->orderBy('id');
    }
}
