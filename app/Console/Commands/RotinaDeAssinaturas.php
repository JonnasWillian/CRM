<?php

namespace App\Console\Commands;

use App\Models\Assinatura;
use App\Services\Assinaturas\AvisosDeAssinatura;
use App\Services\Assinaturas\Faturamento;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Rotina diária de cobrança: gera a fatura de renovação N dias antes do
 * vencimento (Faturamento::garantirRenovacao) e envia os avisos devidos.
 * Cada aviso é registrado em avisos_de_assinatura, então rodar duas vezes
 * no mesmo dia não repete nada e um dia perdido é recuperado no seguinte.
 */
class RotinaDeAssinaturas extends Command
{
    protected $signature = 'assinaturas:rotina';

    protected $description = 'Gera faturas de renovação e envia os avisos de assinatura do dia';

    public function handle(Faturamento $faturamento, AvisosDeAssinatura $avisos): int
    {
        if (! config('comercial.exigir_assinatura')) {
            $this->info('Exigência de assinatura desligada (COMERCIAL_EXIGIR_ASSINATURA): nada a fazer.');

            return self::SUCCESS;
        }

        $falhas = 0;
        Assinatura::query()->chunkById(100, function ($lote) use ($faturamento, $avisos, &$falhas) {
            foreach ($lote as $a) {
                try {
                    $this->processar($a, $faturamento, $avisos);
                } catch (Throwable $e) {
                    $falhas++;
                    report($e);
                    $this->error("Empresa {$a->tenant_id}: {$e->getMessage()}");
                }
            }
        });

        return $falhas === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function processar(Assinatura $a, Faturamento $faturamento, AvisosDeAssinatura $avisos): void
    {
        $dias = $a->diasRestantes();
        $data = fn (?Carbon $c) => $c?->toDateString() ?? '-';

        switch ($a->situacao()) {
            case 'teste':
                if ($marco = self::marco($dias, [7, 3, 1])) {
                    $avisos->avisar($a, 'teste_acabando', "{$marco}d:".$data($a->teste_termina_em), ['dias' => $dias]);
                }
                break;

            case 'ativa':
                if ($a->renovacao_cancelada_em !== null) {
                    if ($dias <= 7) {
                        $avisos->avisar($a, 'plano_termina', $data($a->vigente_ate));
                    }
                    break;
                }
                $faturamento->garantirRenovacao($a);
                $pendente = $faturamento->pendente($a->tenant_id);
                if ($pendente && ($marco = self::marco($dias, [3, 1]))) {
                    $avisos->avisar($a, 'fatura_vencendo', "{$marco}d:".$data($a->vigente_ate), ['dias' => $dias, 'fatura' => $avisos->fatura($pendente)]);
                }
                break;

            case 'carencia':
                $faturamento->garantirRenovacao($a);
                $avisos->avisar($a, 'pagamento_atrasado', $data($a->vigente_ate));
                break;

            default: // teste_expirado, vencida, cancelada: só leitura
                $suspende = $a->suspendeEm();
                $faltam = (int) Carbon::today()->diffInDays($suspende->copy()->startOfDay(), false);
                if ($a->suspensa()) {
                    $avisos->avisar($a, 'suspensa', $data($suspende));
                } elseif ($faltam <= 7) {
                    $avisos->avisar($a, 'suspensao_proxima', $data($suspende));
                } else {
                    $avisos->avisar($a, $a->situacao() === 'teste_expirado' ? 'teste_encerrado' : 'acesso_limitado', $data($a->somenteLeituraDesde()));
                }
        }
    }

    /** O menor marco (em dias) que já foi alcançado; null se nenhum. */
    private static function marco(int $dias, array $marcos): ?int
    {
        $alcancados = array_filter($marcos, fn (int $m) => $dias <= $m);

        return $alcancados === [] ? null : min($alcancados);
    }
}
