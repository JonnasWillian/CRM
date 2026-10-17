<?php

namespace App\Http\Requests\Listagem;

use App\Support\Listagem\Ordenacao;
use App\Support\Listagem\Paginacao;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * Base de toda listagem paginada: page, per_page (teto em Paginacao), sort
 * (lista fechada de colunasOrdenaveis — nada chega ao ORDER BY sem passar
 * por ela), dir e busca. As subclasses declaram os filtros próprios.
 *
 * authorize() é true: visibilidade é dos scopes (visibleTo), não do request.
 */
abstract class ListagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> alias => coluna SQL */
    abstract protected function colunasOrdenaveis(): array;

    /** @return array{0: string, 1: 'asc'|'desc'} */
    protected function ordenacaoPadrao(): array
    {
        return ['created_at', 'desc'];
    }

    /** Regras dos filtros específicos da listagem. */
    protected function filtros(): array
    {
        return [];
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            // Sem max: acima do teto é limitado, não recusado.
            'per_page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', Rule::in(array_keys($this->colunasOrdenaveis()))],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'busca' => ['nullable', 'string', 'max:100'],
            ...$this->filtros(),
        ];
    }

    public function messages(): array
    {
        return [
            'sort.in' => 'Coluna de ordenação inválida.',
            'dir.in' => 'A direção da ordenação é asc ou desc.',
        ];
    }

    public function ordenacao(): Ordenacao
    {
        [$porPadrao, $dirPadrao] = $this->ordenacaoPadrao();
        $por = $this->input('sort') ?: $porPadrao;
        // Com sort explícito e sem dir, sobe (asc); sem sort, vale a direção padrão.
        $dir = $this->input('dir') ?: ($this->input('sort') ? 'asc' : $dirPadrao);

        return new Ordenacao($por, $dir, $this->colunasOrdenaveis()[$por]);
    }

    public function porPagina(?int $padrao = null): int
    {
        return Paginacao::clamp($this->input('per_page'), $padrao);
    }

    /** Os filtros validados, sem os parâmetros de paginação/ordenação. */
    public function filtrosValidados(): array
    {
        return Arr::except($this->validated(), ['page', 'per_page', 'sort', 'dir']);
    }
}
