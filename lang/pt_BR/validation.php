<?php

/*
 * Mensagens padrão de validação em português. As regras de domínio têm
 * mensagens próprias nos FormRequests; estas cobrem o resto (telas de
 * autenticação, regras genéricas).
 */
return [
    'accepted' => 'O campo :attribute deve ser aceito.',
    'after_or_equal' => 'O campo :attribute deve ser uma data igual ou posterior a :date.',
    'array' => 'O campo :attribute deve ser uma lista.',
    'between' => [
        'numeric' => 'O campo :attribute deve estar entre :min e :max.',
        'string' => 'O campo :attribute deve ter entre :min e :max caracteres.',
        'array' => 'O campo :attribute deve ter entre :min e :max itens.',
        'file' => 'O arquivo :attribute deve ter entre :min e :max kilobytes.',
    ],
    'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
    'confirmed' => 'A confirmação de :attribute não confere.',
    'current_password' => 'A senha atual está incorreta.',
    'date' => 'O campo :attribute deve ser uma data válida.',
    'email' => 'Informe um e-mail válido.',
    'exists' => 'O valor escolhido para :attribute é inválido.',
    'file' => 'O campo :attribute deve ser um arquivo.',
    'in' => 'O valor escolhido para :attribute é inválido.',
    'integer' => 'O campo :attribute deve ser um número inteiro.',
    'lowercase' => 'O campo :attribute deve estar em letras minúsculas.',
    'max' => [
        'numeric' => 'O campo :attribute não pode ser maior que :max.',
        'string' => 'O campo :attribute pode ter no máximo :max caracteres.',
        'array' => 'O campo :attribute pode ter no máximo :max itens.',
        'file' => 'O arquivo :attribute pode ter no máximo :max kilobytes.',
    ],
    'mimes' => 'O arquivo :attribute deve ser do tipo: :values.',
    'min' => [
        'numeric' => 'O campo :attribute deve ser pelo menos :min.',
        'string' => 'O campo :attribute deve ter pelo menos :min caracteres.',
        'array' => 'O campo :attribute deve ter pelo menos :min itens.',
        'file' => 'O arquivo :attribute deve ter pelo menos :min kilobytes.',
    ],
    'numeric' => 'O campo :attribute deve ser um número.',
    'password' => [
        'letters' => 'A senha deve ter pelo menos uma letra.',
        'mixed' => 'A senha deve ter letras maiúsculas e minúsculas.',
        'numbers' => 'A senha deve ter pelo menos um número.',
        'symbols' => 'A senha deve ter pelo menos um símbolo.',
        'uncompromised' => 'Esta senha apareceu em um vazamento de dados. Escolha outra.',
    ],
    'present' => 'O campo :attribute deve estar presente.',
    'regex' => 'O formato de :attribute é inválido.',
    'required' => 'O campo :attribute é obrigatório.',
    'required_if' => 'O campo :attribute é obrigatório quando :other é :value.',
    'required_without' => 'O campo :attribute é obrigatório quando :values não está presente.',
    'string' => 'O campo :attribute deve ser um texto.',
    'unique' => 'Este :attribute já está em uso.',
    'uploaded' => 'Não foi possível enviar :attribute.',

    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'current_password' => 'senha atual',
        'password_confirmation' => 'confirmação da senha',
        'empresa' => 'nome da empresa',
        'nome' => 'nome',
        'descricao' => 'descrição',
        'titulo' => 'título',
        'data_limite' => 'vencimento',
        'arquivo' => 'arquivo',
        'papel' => 'papel',
    ],
];
