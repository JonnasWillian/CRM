<?php

namespace App\Rules;

use App\Models\Usuario;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * E-mail único por tenant, contando a lixeira.
 *
 * O índice único (tenant_id, email) continua valendo para o lead excluído —
 * e deve: restaurar não pode colidir. O que muda é a mensagem. A regra
 * `unique:` padrão dizia "já está sendo utilizado" para um lead que o usuário
 * não enxerga mais em lugar nenhum.
 *
 * Passa pelo Eloquent (TenantScope), então só olha o tenant corrente.
 */
class EmailDeLeadDisponivel implements ValidationRule
{
    public function __construct(private readonly ?int $ignorarLeadId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $existente = Usuario::withTrashed()
            ->where('email', $value)
            ->when($this->ignorarLeadId, fn ($q) => $q->whereKeyNot($this->ignorarLeadId))
            ->first(['id', 'deleted_at']);

        if ($existente === null) {
            return;
        }

        $fail($existente->trashed()
            ? 'Existe um lead excluído com este email. Peça a um admin para restaurá-lo em vez de cadastrar de novo.'
            : 'Este email já está sendo utilizado.');
    }
}
