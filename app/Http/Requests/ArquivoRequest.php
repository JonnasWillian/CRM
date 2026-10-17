<?php

namespace App\Http\Requests;

use App\Support\Arquivos\PoliticaDeUpload;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Anexo de LEAD: `POST /api/leads/{usuario}/arquivos`. O lead vem da rota,
 * então a autorização da instância acontece aqui, antes da validação — um
 * 422 contra um lead alheio já revelaria que o id existe no tenant.
 */
class ArquivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('leads.manage')) {
            return false;
        }

        $lead = $this->route('usuario');

        return $lead !== null && $this->user()->can('update', $lead);
    }

    /** 404 só onde há existência a esconder (ver UsuarioRequest::failedAuthorization). */
    protected function failedAuthorization(): void
    {
        $lead = $this->route('usuario');

        if ($lead === null || ($this->user()?->can('view', $lead) ?? false)) {
            parent::failedAuthorization();

            return;
        }

        throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
    }

    public function rules(): array
    {
        return [
            'arquivo' => PoliticaDeUpload::regras(),
            'nome' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return PoliticaDeUpload::mensagens();
    }
}
