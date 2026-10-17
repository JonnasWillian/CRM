<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\Reports\Periodo;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Parâmetros do dashboard e dos relatórios: período, vendedor e funil. */
class MetricasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'periodo' => ['nullable', Rule::in([...Periodo::PRESETS, 'custom'])],
            'de' => ['nullable', 'required_if:periodo,custom', 'date'],
            'ate' => ['nullable', 'required_if:periodo,custom', 'date', 'after_or_equal:de'],
            'user_id' => ['nullable', 'integer'],
            'funil_id' => ['nullable', 'integer', Rule::exists('funis', 'id')->where('tenant_id', app(CurrentTenant::class)->id())],
        ];
    }

    public function messages(): array
    {
        return [
            'periodo.in' => 'Período inválido.',
            'de.required_if' => 'Informe a data inicial do período personalizado.',
            'ate.required_if' => 'Informe a data final do período personalizado.',
            'ate.after_or_equal' => 'A data final precisa ser igual ou posterior à inicial.',
        ];
    }

    public function periodo(): Periodo
    {
        return Periodo::de($this->input('periodo'), $this->input('de'), $this->input('ate'));
    }

    /**
     * Quem tem leads.view-all vê o tenant inteiro (null) ou o vendedor que
     * escolheu; quem não tem vê só a si mesmo, e o parâmetro é ignorado.
     */
    public function agente(): ?User
    {
        $eu = $this->user();

        if (! $eu->can('leads.view-all')) {
            return $eu;
        }

        $userId = $this->integer('user_id');

        return $userId ? User::doTenant(app(CurrentTenant::class)->id())->find($userId) : null;
    }

    public function funilId(): ?int
    {
        return $this->integer('funil_id') ?: null;
    }
}
