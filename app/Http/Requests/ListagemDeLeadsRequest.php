<?php

namespace App\Http\Requests;

use App\Http\Requests\Listagem\ListagemRequest;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Validation\Rule;

/**
 * Filtros da grade de leads. Só valida a FORMA: quem decide o que cada agente
 * enxerga é Usuario::scopeVisibleTo, dentro de ListagemDeLeads.
 */
class ListagemDeLeadsRequest extends ListagemRequest
{
    public const SITUACOES = ['todos', 'ativos', 'ganhos', 'perdidos', 'fechados', 'sem_projeto', 'com_projeto_aberto'];

    protected function colunasOrdenaveis(): array
    {
        return [
            'nome' => 'usuarios.nome',
            'created_at' => 'usuarios.created_at',
            'estagio' => 'estagios.ordem',
            'ultimo_contato' => 'usuarios.ultimo_contato_em',
            'valor_estimado' => 'usuarios.valor_estimado',
            'previsao_fechamento' => 'usuarios.previsao_fechamento',
            'responsavel' => 'users.name',
        ];
    }

    protected function filtros(): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        return [
            'estagios' => ['nullable', 'array'],
            'estagios.*' => ['integer'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
            'situacao' => ['nullable', Rule::in(self::SITUACOES)],
            'funil_id' => ['nullable', 'integer', Rule::exists('funis', 'id')->where('tenant_id', $tenantId)],
            // Responsável: só honrado para quem tem leads.view-all (ver ListagemDeLeads).
            'user_id' => ['nullable', 'integer'],
            'estagnados' => ['nullable', 'boolean'],
            'previsao_de' => ['nullable', 'date'],
            'previsao_ate' => ['nullable', 'date', 'after_or_equal:previsao_de'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'ate.after_or_equal' => 'A data final precisa ser igual ou posterior à inicial.',
            'previsao_ate.after_or_equal' => 'A previsão final precisa ser igual ou posterior à inicial.',
        ];
    }
}
