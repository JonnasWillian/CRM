<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Perdas\Perdivel;
use App\Support\Projetos\CronogramaDeRecorrencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Projeto extends Model implements Perdivel
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $appends = ['recorrencia'];

    protected $fillable = [
        'nome',
        'descricao',
        'preco',
        'tipo',
        'valor_mensal',
        'duracao_meses',
        'data_inicial',
        'data_final',
        'parcelas',
        'qtd_parcelas',
        'usuario_id',
        'status_id',
    ];

    protected function casts(): array
    {
        return [
            'preco' => 'decimal:2',
            'data_inicial' => 'date:Y-m-d',
            'data_final' => 'date:Y-m-d',
            'parcelas' => 'boolean',
            'qtd_parcelas' => 'integer',
            'fechado_em' => 'datetime',
            'cancelado_em' => 'datetime',
            'valor_mensal' => 'decimal:2',
            'duracao_meses' => 'integer',
        ];
    }

    public function status()
    {
        return $this->belongsTo(StatusProjeto::class, 'status_id')->withTrashed();
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function perdas(): MorphMany
    {
        return $this->morphMany(Perda::class, 'perdivel');
    }

    public function anotacoes()
    {
        return $this->hasMany(ProjetoAnotacao::class, 'projeto_id');
    }

    public function aditivos()
    {
        return $this->hasMany(ProjetoAditivo::class, 'projeto_id')->orderBy('id');
    }

    public function ehRecorrente(): bool
    {
        return $this->tipo === 'recorrente';
    }

    /**
     * O contrato recorrente em meses (null para projeto único). Usa os
     * aditivos carregados quando houver; senão consulta.
     */
    public function cronograma(): ?CronogramaDeRecorrencia
    {
        if (! $this->ehRecorrente() || $this->data_inicial === null || ! $this->duracao_meses) {
            return null;
        }

        $aditivos = $this->relationLoaded('aditivos') ? $this->aditivos : $this->aditivos()->get();

        return new CronogramaDeRecorrencia(
            $this->data_inicial,
            [
                ['meses' => $this->duracao_meses, 'valor_mensal' => $this->valor_mensal],
                ...$aditivos->map(fn (ProjetoAditivo $a) => ['meses' => $a->meses, 'valor_mensal' => $a->valor_mensal])->all(),
            ],
            now(),
            $this->cancelado_em,
        );
    }

    /** Na resposta JSON: o resumo do contrato recorrente (ou null). */
    public function getRecorrenciaAttribute(): ?array
    {
        return $this->cronograma()?->toArray();
    }

    public function anexos()
    {
        return $this->hasMany(ProjetoAnexo::class, 'projeto_id');
    }

    /**
     * Para o projeto, "perdido" é `is_lost` do status — o eixo que já existia
     * antes dos funis e que não mudou. Só a exigência do motivo é nova.
     */
    public function estadoAtualEhPerda(): bool
    {
        return (bool) $this->status?->is_lost;
    }

    public function estadoSeriaPerda(array $atributos): bool
    {
        $statusId = array_key_exists('status_id', $atributos)
            ? $atributos['status_id']
            : $this->status_id;

        if ($statusId === null) {
            return false;
        }

        return (bool) StatusProjeto::withTrashed()->whereKey($statusId)->value('is_lost');
    }

    public function valorDaPerda(): ?float
    {
        return $this->preco !== null ? (float) $this->preco : null;
    }
}
