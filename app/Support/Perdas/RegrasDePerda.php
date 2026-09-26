<?php

namespace App\Support\Perdas;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * As regras de validação e a leitura do bloco `perda` do payload, num lugar só.
 *
 * Seis endpoints aceitam esse bloco. Escrever as regras em cada um faria o
 * contrato divergir com o tempo — um aceitaria observação de 500 caracteres,
 * outro de 255, e o frontend descobriria isso em produção.
 *
 * O bloco é aninhado (`perda: { motivo_perda_id, observacao }`) em vez de dois
 * campos soltos porque `observacao` no nível de cima colidiria de significado
 * com a `descricao` do lead e do projeto, que já estão no mesmo payload.
 */
class RegrasDePerda
{
    public const MAX_OBSERVACAO = 500;

    /**
     * Sempre `nullable`: quem decide se o motivo é obrigatório é a transição,
     * não o formulário. Editar o nome de um lead perdido não pede motivo de
     * novo; só entrar em perda pede. Essa decisão é do AplicarTransicao, que
     * enxerga o estado anterior — o validador não enxerga.
     *
     * @return array<string, mixed>
     */
    public static function campos(): array
    {
        return [
            'perda' => ['nullable', 'array'],
            'perda.motivo_perda_id' => [
                'nullable',
                'integer',
                Rule::exists('motivos_perda', 'id')
                    ->where('tenant_id', app(CurrentTenant::class)->id())
                    ->whereNull('deleted_at'),
            ],
            'perda.observacao' => ['nullable', 'string', 'max:'.self::MAX_OBSERVACAO],
        ];
    }

    /**
     * @return array{motivo_perda_id: int|null, observacao: string|null}
     */
    public static function extrair(Request $request): array
    {
        $motivoId = $request->input('perda.motivo_perda_id');

        return [
            'motivo_perda_id' => $motivoId === null ? null : (int) $motivoId,
            'observacao' => $request->input('perda.observacao'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mensagens(): array
    {
        return [
            'perda.motivo_perda_id.exists' => 'Motivo de perda inválido ou arquivado.',
            'perda.observacao.max' => 'A observação deve ter no máximo :max caracteres.',
        ];
    }
}
