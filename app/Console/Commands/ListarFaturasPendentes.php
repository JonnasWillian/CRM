<?php

namespace App\Console\Commands;

use App\Models\Fatura;
use Illuminate\Console\Command;

/** O que a equipe comercial tem para cobrar, das mais antigas para as mais novas. */
class ListarFaturasPendentes extends Command
{
    protected $signature = 'faturas:pendentes';

    protected $description = 'Lista as faturas aguardando pagamento';

    public function handle(): int
    {
        $faturas = Fatura::with('tenant')->where('status', 'pendente')->orderBy('vence_em')->orderBy('id')->get();
        if ($faturas->isEmpty()) {
            $this->info('Nenhuma fatura pendente.');

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'Empresa', 'Plano', 'Ciclo', 'Usuários', 'Valor', 'Vence em', 'Situação'],
            $faturas->map(fn (Fatura $f) => [
                $f->id,
                $f->tenant?->nome,
                $f->planoNome(),
                $f->ciclo,
                $f->usuarios,
                Fatura::emReais($f->valor),
                $f->vence_em->format('d/m/Y'),
                $f->atrasada() ? 'atrasada' : 'em dia',
            ])->all(),
        );
        $this->line('Para confirmar: php artisan fatura:pagar <#> --referencia="..."');

        return self::SUCCESS;
    }
}
