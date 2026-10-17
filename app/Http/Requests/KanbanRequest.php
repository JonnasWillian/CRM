<?php

namespace App\Http\Requests;

use App\Support\Listagem\Paginacao;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filtros do quadro: funil, busca (`q`), responsável (só com leads.view-all),
 * "só parados" e cards por coluna (teto em config). `funil_id` inexistente é
 * 404 no controller, não 422: quem pede um quadro que não existe recebe
 * "não existe", não "parâmetro inválido".
 */
class KanbanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'funil_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer'],
            'estagnados' => ['nullable', 'boolean'],
            'por_coluna' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** @return array{busca:?string, user_id:?int, estagnados:bool, por_coluna:int} */
    public function filtros(): array
    {
        return [
            'busca' => $this->input('q') ?: null,
            'user_id' => $this->user()->can('leads.view-all') ? ($this->integer('user_id') ?: null) : null,
            'estagnados' => $this->boolean('estagnados'),
            'por_coluna' => min(
                Paginacao::clamp($this->input('por_coluna'), (int) config('leads.kanban.cards_por_coluna', 30)),
                (int) config('leads.kanban.max_cards_por_coluna', 100),
            ),
        ];
    }
}
