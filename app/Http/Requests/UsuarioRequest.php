<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        if ($this->isMethod('post')) {
            return $this->storeRules();
        }

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return $this->updateRules();
        }

        return [];
    }

    private function storeRules()
    {
        return [
            'nome' => 'required|string|min:5',
            'email' => 'required|email|unique:usuarios,email',
            'telefone' => 'required|string',
            'descricao' => 'nullable|string',
            'tag_id' => 'nullable',
            'user_id' => 'required',
        ];
    }

    private function updateRules()
    {
        $id = $this->request->get('id');

        return [
            'nome' => 'required|string|min:5',
            'email' => 'required|email|unique:usuarios,email,' . $id,
            'telefone' => 'required|string|min:7',
            'descricao' => 'nullable|string',
            'tag_id' => 'nullable',
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O campo nome é obrigatório.',
            'nome.min' => 'O nome deve ter no mínimo :min caracteres.',
            'nome.string' => 'O nome deve ser um texto válido.',

            'email.required' => 'O email é obrigatório.',
            'email.email' => 'Informe um email válido.',
            'email.unique' => 'Este email já está sendo utilizado.',

            'telefone.required' => 'O telefone é obrigatório.',
            'telefone.min' => 'O telefone deve ter pelo menos :min caracteres.',
            'telefone.string' => 'O telefone deve ser um texto válido.',

            'descricao.string' => 'A descrição deve ser um texto válido.',

            'user_id.required' => 'O usuário responsável é obrigatório.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nome' => 'nome',
            'email' => 'email',
            'telefone' => 'telefone',
            'descricao' => 'descrição',
            'user_id' => 'usuário',
        ];
    }
}