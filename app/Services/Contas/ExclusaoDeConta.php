<?php

namespace App\Services\Contas;

use App\Models\User;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quando um agente pode deixar o sistema.
 *
 * O Breeze trata a conta como dona dos próprios dados. Aqui a carteira é da
 * EMPRESA: sair não pode levá-la (o banco já recusa — RESTRICT em
 * usuarios.user_id), e o tenant não pode ficar sem ninguém que o administre.
 *
 * Enquanto a transferência de leads (L2) não existir, quem tem leads não sai.
 * É o comportamento seguro; L2 troca "peça a um admin" por um botão.
 */
class ExclusaoDeConta
{
    /** @return list<string> */
    public function impedimentos(User $user): array
    {
        $impedimentos = [];

        // withTrashed: o RESTRICT também conta os leads da lixeira.
        $leads = Usuario::withTrashed()->where('user_id', $user->id)->count();
        if ($leads > 0) {
            $impedimentos[] = $leads === 1
                ? 'Você é dono de 1 lead (contando a lixeira). Peça a um admin para transferi-lo antes de excluir a conta.'
                : "Você é dono de {$leads} leads (contando a lixeira). Peça a um admin para transferi-los antes de excluir a conta.";
        }

        if ($this->ehUltimoAdmin($user)) {
            $impedimentos[] = 'Você é o único admin da empresa. Outra pessoa precisa ser admin antes de você excluir a conta.';
        }

        return $impedimentos;
    }

    public function excluir(User $user): void
    {
        $impedimentos = $this->impedimentos($user);

        if ($impedimentos !== []) {
            throw ValidationException::withMessages(['conta' => $impedimentos]);
        }

        DB::transaction(fn () => $user->delete());
    }

    /**
     * Papéis são por tenant (spatie em modo teams). O team corrente é o do
     * usuário — IdentifyTenant o define em toda requisição autenticada.
     */
    private function ehUltimoAdmin(User $user): bool
    {
        if (! $user->hasRole('admin')) {
            return false;
        }

        return ! User::role('admin')
            ->where('tenant_id', $user->tenant_id)
            ->whereKeyNot($user->id)
            ->exists();
    }
}
