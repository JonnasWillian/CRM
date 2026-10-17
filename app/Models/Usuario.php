<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Perdas\Perdivel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Usuario extends Model implements Perdivel
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'nome',
        'email',
        'descricao',
        'telefone',
        'user_id',
        'funil_id',
        'estagio_id',
        'valor_estimado',
        'valor_fechado',
        'probabilidade',
        'previsao_fechamento',
    ];

    protected function casts(): array
    {
        return [
            'valor_estimado' => 'decimal:2',
            'valor_fechado' => 'decimal:2',
            'probabilidade' => 'integer',
            'previsao_fechamento' => 'date:Y-m-d',
            'ultimo_contato_em' => 'datetime',
            'fechado_em' => 'datetime',
        ];
    }

    /** O agente dono do lead (`usuarios.user_id`). */
    public function responsavel()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estagio()
    {
        return $this->belongsTo(Estagio::class, 'estagio_id')->withTrashed();
    }

    public function funil()
    {
        return $this->belongsTo(Funil::class, 'funil_id')->withTrashed();
    }

    public function perdas(): MorphMany
    {
        return $this->morphMany(Perda::class, 'perdivel');
    }

    public function projetos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Projeto::class, 'usuario_id');
    }

    public function anotacoes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Anotacao::class, 'usuario_id');
    }

    public function arquivos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(arquivo::class, 'usuario_id');
    }

    public function tarefas(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Tarefa::class, 'usuario_id');
    }

    /**
     * Para o lead, "perdido" é o tipo do estágio em que ele está.
     *
     * `estagio()` inclui arquivados de propósito: um estágio perdido que foi
     * arquivado enquanto ainda segurava leads continua sendo um estágio
     * perdido, e o lead que está nele continua perdido.
     */
    public function estadoAtualEhPerda(): bool
    {
        return $this->estagio?->tipo === Estagio::TIPO_PERDIDO;
    }

    public function estadoSeriaPerda(array $atributos): bool
    {
        $estagioId = array_key_exists('estagio_id', $atributos)
            ? $atributos['estagio_id']
            : $this->estagio_id;

        if ($estagioId === null) {
            return false;
        }

        // Passa pelo TenantScope: um id de outro tenant não resolve e a
        // resposta é "não é perda" — a validação do endpoint é quem recusa o
        // id inválido, não esta função.
        return Estagio::withTrashed()->whereKey($estagioId)->value('tipo') === Estagio::TIPO_PERDIDO;
    }

    /**
     * O que se perde quando um lead é perdido é o dinheiro que ainda estava em
     * jogo: a soma dos projetos dele que não foram nem ganhos nem perdidos.
     * Projeto já fechado não entra — ele não foi perdido junto.
     */
    public function valorDaPerda(): ?float
    {
        $soma = Projeto::where('usuario_id', $this->id)
            ->whereHas('status', fn ($q) => $q->open())
            ->sum('preco');

        return $soma > 0 ? (float) $soma : null;
    }

    /**
     * Restringe a listagem ao que o agente pode enxergar.
     *
     * Com `leads.view-all` não há filtro adicional — o TenantScope já limita à
     * empresa. Sem a permission, o agente vê só a própria carteira.
     *
     * Este scope serve as LISTAGENS. O acesso a um lead específico é decidido
     * pela UsuarioPolicy, que aplica a mesma regra para uma linha só.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('leads.view-all')) {
            return $query;
        }

        return $query->where('usuarios.user_id', $user->id);
    }

    /**
     * Os ids que o agente enxerga, para usar como subconsulta de um
     * `whereIn('usuario_id', ...)` noutra tabela (projetos, tarefas, etc).
     *
     * Devolve o Builder, não a lista: passado direto a `whereIn`, o banco
     * resolve a visibilidade dentro da própria consulta. Resolver antes com
     * `pluck('id')` manda a carteira inteira como bindings.
     */
    public static function idsVisiveisPara(User $user): Builder
    {
        return static::visibleTo($user)->select('usuarios.id');
    }
}
