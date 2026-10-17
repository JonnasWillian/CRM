<?php

namespace App\Http\Requests;

use App\Support\Arquivos\PoliticaDeUpload;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Anexo de PROJETO: `POST /api/projetos/{projeto}/anexos`. Fim do campo
 * `usuario_id` que carregava um id de projeto.
 */
class ProjetoAnexoRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('projetos.manage')) {
            return false;
        }

        $projeto = $this->route('projeto');

        return $projeto !== null && $this->user()->can('update', $projeto);
    }

    protected function failedAuthorization(): void
    {
        $projeto = $this->route('projeto');

        if ($projeto === null || ($this->user()?->can('view', $projeto) ?? false)) {
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
