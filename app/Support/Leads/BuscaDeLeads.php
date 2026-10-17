<?php

namespace App\Support\Leads;

use Illuminate\Database\Eloquent\Builder;

/** O mesmo "buscar" da grade, do quadro e das ações em massa: nome, e-mail, descrição e dígitos do telefone. */
final class BuscaDeLeads
{
    public static function aplicar(Builder $q, string $busca): Builder
    {
        // % e _ do usuário são texto, não curinga.
        $termo = '%'.addcslashes($busca, '%_\\').'%';
        $digitos = preg_replace('/\D/', '', $busca);

        return $q->where(function (Builder $w) use ($termo, $digitos) {
            $w->where('usuarios.nome', 'like', $termo)
                ->orWhere('usuarios.email', 'like', $termo)
                ->orWhere('usuarios.descricao', 'like', $termo);

            // Telefone é E.164 no banco: "(11) 98888" vira "1198888".
            if (strlen($digitos) >= 3) {
                $w->orWhere('usuarios.telefone', 'like', '%'.$digitos.'%');
            }
        });
    }
}
