<?php

namespace App\Http\Requests\Leads;

use App\Models\Estagio;
use App\Support\Perdas\RegrasDePerda;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Mover N leads para um estágio. Destino perdido exige um motivo, que vale
 * para todos — e é recusado aqui, antes de tocar em qualquer lead, para
 * não deixar metade da seleção movida.
 */
class MoverEstagioEmMassaRequest extends SelecaoDeLeadsRequest
{
    protected function regrasDaAcao(): array
    {
        return [
            'estagio_id' => ['required', 'integer', Rule::exists('estagios', 'id')->where('tenant_id', app(CurrentTenant::class)->id())->whereNull('deleted_at')],
            ...RegrasDePerda::campos(),
        ];
    }

    public function messages(): array
    {
        return [...parent::messages(), ...RegrasDePerda::mensagens(), 'estagio_id.exists' => 'Estágio inválido ou arquivado.'];
    }

    public function after(): array
    {
        return [
            function (Validator $v) {
                if ($v->errors()->isNotEmpty()) {
                    return;
                }
                if ($this->destino()->tipo === Estagio::TIPO_PERDIDO && ! $this->input('perda.motivo_perda_id')) {
                    $v->errors()->add('perda.motivo_perda_id', 'Informe o motivo da perda.');
                }
            },
        ];
    }

    public function destino(): Estagio
    {
        return Estagio::findOrFail($this->integer('estagio_id'));
    }
}
