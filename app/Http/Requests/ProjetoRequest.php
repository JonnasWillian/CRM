<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProjetoRequest extends FormRequest
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
            'descricao' => 'nullable|string',
            'status_id' => 'required',
            'usuario_id' => 'required',
        ];
    }

    private function updateRules()
    {
        return [
            'nome' => 'required|string|min:5',
            'descricao' => 'nullable|string',
            'status_id' => 'required',
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'O campo nome é obrigatório.',
            'nome.min' => 'O nome deve ter no mínimo :min caracteres.',
            'nome.string' => 'O nome deve ser um texto válido.',

            'descricao.string' => 'A descrição deve ser um texto válido.',

            'usuario_id.required' => 'O usuário responsável é obrigatório.',
            'status_id.required' => 'O Status é obrigatório.',
        ];
    }

    public function attributes(): array
    {
        return [
            'nome' => 'nome',
            'descricao' => 'descrição',
            'usuario_id' => 'usuário',
            'status_id' => 'status',
        ];
    }
}