<?php

namespace App\Http\Requests\Leads;

use App\Models\User;
use App\Support\Equipe\RegrasDeAdmin;
use App\Support\Tenancy\CurrentTenant;

/** Transferir N leads para um membro ativo. Só quem vê a empresa inteira (leads.view-all). */
class TransferirEmMassaRequest extends SelecaoDeLeadsRequest
{
    public function authorize(): bool
    {
        return parent::authorize() && $this->user()->can('leads.view-all');
    }

    protected function regrasDaAcao(): array
    {
        return ['user_id' => ['required', 'integer', RegrasDeAdmin::regraDeMembroAtivo(app(CurrentTenant::class)->id())]];
    }

    public function messages(): array
    {
        return [...parent::messages(), 'user_id.exists' => 'Escolha um membro ativo da empresa.', 'user_id.required' => 'Escolha para quem transferir.'];
    }

    public function destino(): User
    {
        return User::findOrFail($this->integer('user_id'));
    }
}
