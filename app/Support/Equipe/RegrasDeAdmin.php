<?php

namespace App\Support\Equipe;

use App\Models\User;

/** A empresa nunca fica sem admin ativo. Usado por exclusão de conta, troca de papel e desativação. */
final class RegrasDeAdmin
{
    public static function ehUltimoAdmin(User $user): bool
    {
        if (! $user->hasRole('admin')) {
            return false;
        }

        return ! User::role('admin')
            ->where('tenant_id', $user->tenant_id)
            ->where('ativo', true)
            ->whereKeyNot($user->id)
            ->exists();
    }

    /** Membros ativos da empresa (destino de transferência, selects de responsável). */
    public static function regraDeMembroAtivo(int $tenantId): \Illuminate\Validation\Rules\Exists
    {
        return \Illuminate\Validation\Rule::exists('users', 'id')->where('tenant_id', $tenantId)->where('ativo', true);
    }
}
