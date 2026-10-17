<?php

namespace App\Support\Leads;

use App\Rules\EmailDeLeadDisponivel;
use App\Rules\TelefoneE164;
use App\Support\LimitesDeTexto;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Validation\Rule;

/**
 * Regras de validação do lead, num lugar só: o UsuarioRequest (cadastro e
 * edição pela tela) e o job de importação (uma linha do CSV por vez) validam
 * exatamente o mesmo conjunto.
 */
final class RegrasDeLead
{
    /**
     * @param  int|null  $ignorarLeadId  na edição, o próprio lead não conta como e-mail em uso
     * @param  int|null  $funilId  quando informado, o estágio precisa pertencer a ele
     */
    public static function campos(?int $ignorarLeadId = null, ?int $funilId = null): array
    {
        $tenantId = app(CurrentTenant::class)->id();

        $estagio = Rule::exists('estagios', 'id')->where('tenant_id', $tenantId);
        if ($funilId !== null) {
            $estagio->where('funil_id', $funilId);
        }

        return [
            'nome' => ['required', 'string', 'min:5', 'max:'.LimitesDeTexto::NOME],
            'email' => ['required', 'email', 'max:'.LimitesDeTexto::EMAIL, new EmailDeLeadDisponivel($ignorarLeadId)],
            'telefone' => ['nullable', 'string', new TelefoneE164],
            'descricao' => ['nullable', 'string', 'max:'.LimitesDeTexto::DESCRICAO],
            'funil_id' => ['nullable', Rule::exists('funis', 'id')->where('tenant_id', $tenantId)->whereNull('deleted_at')],
            'estagio_id' => ['nullable', $estagio],
            'valor_estimado' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'valor_fechado' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'probabilidade' => ['nullable', 'integer', 'between:0,100'],
            'previsao_fechamento' => ['nullable', 'date'],
        ];
    }

    public static function mensagens(): array
    {
        return [
            'nome.required' => 'O campo nome é obrigatório.',
            'nome.min' => 'O nome deve ter no mínimo :min caracteres.',
            'nome.string' => 'O nome deve ser um texto válido.',
            'nome.max' => 'O nome pode ter no máximo :max caracteres.',

            'email.required' => 'O email é obrigatório.',
            'email.email' => 'Informe um email válido.',
            'email.max' => 'O email pode ter no máximo :max caracteres.',

            'telefone.string' => 'O telefone deve ser um texto válido.',

            'descricao.string' => 'A descrição deve ser um texto válido.',
            'descricao.max' => 'A descrição pode ter no máximo :max caracteres.',

            'valor_estimado.numeric' => 'Informe o valor estimado em reais, sem símbolo.',
            'valor_estimado.min' => 'O valor estimado não pode ser negativo.',
            'valor_fechado.numeric' => 'Informe o valor fechado em reais, sem símbolo.',
            'valor_fechado.min' => 'O valor fechado não pode ser negativo.',
            'probabilidade.integer' => 'A probabilidade é um número inteiro de 0 a 100.',
            'probabilidade.between' => 'A probabilidade vai de 0 a 100.',
            'previsao_fechamento.date' => 'Informe uma data válida para a previsão de fechamento.',
        ];
    }
}
