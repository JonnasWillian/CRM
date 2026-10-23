<?php

namespace App\Services\Assinaturas;

use App\Exceptions\LimiteDoPlanoException;
use App\Models\Assinatura;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Regras de assinatura: teste ao nascer, ativar/renovar um plano, cancelar,
 * uso × limites. A cobrança (gateway) ainda não existe: ativar() é chamado
 * pela escolha do plano na tela e pelo comando `assinatura:ativar`; quando
 * o gateway entrar, é ele quem chamará ativar() ao confirmar o pagamento.
 */
class Assinaturas
{
    public const CICLOS = ['mensal', 'anual'];

    /** A assinatura da empresa; cria o teste se ainda não houver (empresas anteriores ao recurso). */
    public function da(Tenant|int $tenant): Assinatura
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return Assinatura::firstOrCreate(['tenant_id' => $id], [
            'plano' => 'inicial',
            'status' => 'teste',
            'teste_termina_em' => Carbon::now()->addDays((int) config('comercial.dias_de_teste', 14)),
        ]);
    }

    public function iniciarTeste(Tenant $tenant, ?string $planoPretendido = null): Assinatura
    {
        $a = $this->da($tenant);
        if ($planoPretendido !== null && $this->planoExiste($planoPretendido)) {
            $a->plano = $planoPretendido;
            $a->save();
        }

        return $a;
    }

    /**
     * Ativa ou renova. Renovar antes do vencimento soma ao vencimento atual;
     * depois dele (ou no teste), conta a partir de hoje.
     */
    public function ativar(Tenant|int $tenant, string $plano, string $ciclo): Assinatura
    {
        if (! $this->planoExiste($plano)) {
            throw new InvalidArgumentException("Plano desconhecido: {$plano}");
        }
        if (! in_array($ciclo, self::CICLOS, true)) {
            throw new InvalidArgumentException("Ciclo desconhecido: {$ciclo}");
        }

        $a = $this->da($tenant);
        $base = $a->situacao() === 'ativa' ? $a->vigente_ate->copy() : Carbon::now();

        $a->fill([
            'plano' => $plano,
            'ciclo' => $ciclo,
            'status' => 'ativa',
            'vigente_ate' => $ciclo === 'anual' ? $base->addYear() : $base->addMonthNoOverflow(),
            'ativada_em' => $a->ativada_em ?? Carbon::now(),
            'cancelada_em' => null,
        ])->save();

        return $a;
    }

    public function cancelar(Tenant|int $tenant): Assinatura
    {
        $a = $this->da($tenant);
        $a->fill(['status' => 'cancelada', 'cancelada_em' => Carbon::now()])->save();

        return $a;
    }

    /** @return array{usuarios:int, funis:int, leads:int} */
    public function uso(Tenant|int $tenant): array
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return [
            'usuarios' => DB::table('users')->where('tenant_id', $id)->where('ativo', true)->count(),
            'funis' => DB::table('funis')->where('tenant_id', $id)->whereNull('deleted_at')->count(),
            'leads' => DB::table('usuarios')->where('tenant_id', $id)->whereNull('deleted_at')->count(),
        ];
    }

    /**
     * Recusa quando o plano não comporta mais `$acrescimo` do recurso.
     *
     * @param  'usuarios'|'funis'|'leads'  $recurso
     *
     * @throws LimiteDoPlanoException
     */
    public function verificarLimite(Tenant|int $tenant, string $recurso, int $acrescimo = 1): void
    {
        if (! config('comercial.exigir_assinatura')) {
            return;
        }

        $a = $this->da($tenant);
        $limite = $a->limites()[$recurso] ?? null;
        if ($limite === null) {
            return;
        }

        $uso = $this->uso($tenant)[$recurso];
        if ($uso + $acrescimo <= $limite) {
            return;
        }

        $nome = $a->planoConfig()['nome'] ?? $a->plano;
        $rotulo = ['usuarios' => 'usuários ativos', 'funis' => 'funis', 'leads' => 'leads'][$recurso];

        throw new LimiteDoPlanoException($recurso, "O plano {$nome} permite até {$limite} {$rotulo} e você já tem {$uso}. Troque de plano em Configurações › Assinatura para continuar.");
    }

    /** O que o front recebe (props compartilhadas e página de assinatura). */
    public function resumo(Tenant|int $tenant): array
    {
        $a = $this->da($tenant);
        $plano = $a->planoConfig();

        return [
            'status' => $a->situacao(),
            'plano' => $a->plano,
            'plano_nome' => $plano['nome'] ?? $a->plano,
            'ciclo' => $a->ciclo,
            'dias_restantes' => $a->diasRestantes(),
            'teste_termina_em' => $a->teste_termina_em?->toDateString(),
            'vigente_ate' => $a->vigente_ate?->toDateString(),
            'bloqueada' => $a->bloqueada(),
            'limites' => $a->limites(),
            'uso' => $this->uso($tenant),
        ];
    }

    public function planoExiste(string $chave): bool
    {
        return collect(config('comercial.planos', []))->contains('chave', $chave);
    }
}
