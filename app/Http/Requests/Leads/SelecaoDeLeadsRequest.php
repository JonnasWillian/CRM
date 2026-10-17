<?php

namespace App\Http\Requests\Leads;

use App\Http\Requests\ListagemDeLeadsRequest;
use App\Models\Usuario;
use App\Queries\Leads\ListagemDeLeads;
use App\Support\Listagem\Ordenacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * Seleção de uma ação em massa: `ids[]` (até o teto) ou `todos=true` com
 * os `filtros` da grade — os mesmos de ListagemDeLeadsRequest, validados
 * aqui sob o prefixo `filtros.`. Em qualquer caso só entram os leads que
 * o agente enxerga (visibleTo); os outros são ignorados e contados.
 */
class SelecaoDeLeadsRequest extends FormRequest
{
    public const TETO_DE_IDS = 200;

    public function authorize(): bool
    {
        return $this->user()?->can('leads.manage') ?? false;
    }

    public function rules(): array
    {
        $daGrade = Arr::except((new ListagemDeLeadsRequest)->rules(), ['page', 'per_page', 'sort', 'dir']);
        $filtros = [];
        foreach ($daGrade as $campo => $regras) {
            $filtros['filtros.'.$campo] = array_map(
                fn ($r) => is_string($r) ? preg_replace('/^after_or_equal:(\w+)$/', 'after_or_equal:filtros.$1', $r) : $r,
                $regras,
            );
        }

        return [
            'ids' => ['required_without:todos', 'array', 'max:'.self::TETO_DE_IDS],
            'ids.*' => ['integer'],
            'todos' => ['nullable', 'boolean'],
            'filtros' => ['nullable', 'array'],
            ...$filtros,
            ...$this->regrasDaAcao(),
        ];
    }

    /** Regras específicas da ação (estágio destino, responsável…). */
    protected function regrasDaAcao(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [
            'ids.required_without' => 'Selecione ao menos um lead.',
            'ids.max' => 'Selecione no máximo :max leads por vez — ou use "todos os resultados".',
        ];
    }

    public function porFiltro(): bool
    {
        return $this->boolean('todos');
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_values(array_unique(array_map('intval', $this->input('ids', []))));
    }

    /** Os leads selecionados que o agente enxerga, ordenados por id (chunkById). */
    public function selecao(): Builder
    {
        if ($this->porFiltro()) {
            return app(ListagemDeLeads::class)
                ->query($this->user(), $this->validated()['filtros'] ?? [], new Ordenacao('id', 'asc', 'usuarios.id'))
                ->without(['estagio', 'responsavel']);
        }

        return Usuario::visibleTo($this->user())->whereIn('usuarios.id', $this->ids())->orderBy('usuarios.id');
    }

    /** Quantos leads a seleção pedia (para contar os ignorados). */
    public function tamanhoPedido(): int
    {
        return $this->porFiltro() ? (clone $this->selecao())->count() : count($this->ids());
    }
}
