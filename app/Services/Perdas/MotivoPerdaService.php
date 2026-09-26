<?php

namespace App\Services\Perdas;

use App\Exceptions\RegraDePerdaException;
use App\Models\MotivoPerda;
use Illuminate\Support\Facades\DB;

/**
 * Manutenção do catálogo de motivos.
 *
 * A invariante protegida é a mesma classe de problema que "todo funil precisa
 * de um estágio aberto": **o tenant precisa de ao menos um motivo disponível**.
 * Arquivar o último tornaria impossível perder qualquer coisa, e a falha
 * apareceria longe daqui — no vendedor tentando arrastar um card e recebendo
 * um erro que ele não tem como resolver.
 */
class MotivoPerdaService
{
    public function criar(array $dados): MotivoPerda
    {
        $motivo = new MotivoPerda();
        $motivo->fill($dados);
        $motivo->ordem = $dados['ordem'] ?? ((int) MotivoPerda::withTrashed()->max('ordem') + 1);
        $motivo->save();

        return $motivo;
    }

    public function atualizar(MotivoPerda $motivo, array $dados): MotivoPerda
    {
        $motivo->fill($dados);
        $motivo->save();

        return $motivo;
    }

    /**
     * Soft delete. O motivo some do seletor de quem registra uma perda nova e
     * continua nomeando as perdas antigas — por isso a FK em `perdas` é
     * restrict e nada é removido fisicamente.
     */
    public function arquivar(MotivoPerda $motivo): void
    {
        $sobram = MotivoPerda::whereKeyNot($motivo->getKey())->exists();

        if (! $sobram) {
            throw new RegraDePerdaException('Este é o último motivo de perda disponível. Crie outro antes de arquivar este.');
        }

        $motivo->delete();
    }

    public function restaurar(MotivoPerda $motivo): void
    {
        $motivo->restore();
    }

    /**
     * @param  array<int, int>  $idsNaOrdem
     */
    public function reordenar(array $idsNaOrdem): void
    {
        DB::transaction(function () use ($idsNaOrdem) {
            foreach ($idsNaOrdem as $posicao => $id) {
                // O where passa pelo TenantScope: um id de outro tenant não
                // casa e nenhuma linha é tocada.
                MotivoPerda::withTrashed()->whereKey($id)->update(['ordem' => $posicao + 1]);
            }
        });
    }
}
