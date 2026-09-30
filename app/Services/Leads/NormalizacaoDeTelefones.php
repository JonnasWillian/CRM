<?php

namespace App\Services\Leads;

use App\Rules\TelefoneE164;
use App\Support\Telefone;
use Illuminate\Support\Facades\DB;

/**
 * Converte os telefones gravados para E.164 com a MESMA função que valida as
 * entradas novas (Telefone::normalizar). O que não é reconhecido é relatado e
 * mantido como está — nunca descartado.
 *
 * DB::table e não Eloquent: roda na migration e no console, sem tenant ativo,
 * e não deve disparar observers (não é uma edição do lead por alguém).
 */
class NormalizacaoDeTelefones
{
    /** @return array{alterados:int, invalidos:list<array{id:int,telefone:string}>} */
    public function executar(bool $simular): array
    {
        $alterados = 0;
        $invalidos = [];

        DB::table('usuarios')->whereNotNull('telefone')
            ->chunkById(500, function ($linhas) use ($simular, &$alterados, &$invalidos) {
                foreach ($linhas as $linha) {
                    $atual = (string) $linha->telefone;
                    $novo = Telefone::normalizar($atual);

                    if (! TelefoneE164::valido($novo)) {
                        $invalidos[] = ['id' => (int) $linha->id, 'telefone' => $atual];
                        continue;
                    }

                    if ($novo === $atual) {
                        continue;
                    }

                    $alterados++;

                    if (! $simular) {
                        DB::table('usuarios')->where('id', $linha->id)->update(['telefone' => $novo]);
                    }
                }
            });

        return ['alterados' => $alterados, 'invalidos' => $invalidos];
    }
}
