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
     * Ativa ou renova. Chamado só pelo pagamento confirmado (Faturamento::pagar).
     * Renovar antes do vencimento ou na carência soma ao vencimento atual;
     * pagar durante o teste começa a contar no fim do teste; depois disso,
     * a partir de hoje.
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
        $base = match ($a->situacao()) {
            // Renovar antes do fim ou na carência continua do vencimento: os
            // dias de carência foram usados, não são bônus.
            'ativa', 'carencia' => $a->vigente_ate->copy(),
            'teste' => $a->teste_termina_em->copy(),
            default => Carbon::now(),
        };

        $a->fill([
            'plano' => $plano,
            'ciclo' => $ciclo,
            'status' => 'ativa',
            'vigente_ate' => $ciclo === 'anual' ? $base->addYear() : $base->addMonthNoOverflow(),
            'ativada_em' => $a->ativada_em ?? Carbon::now(),
            'cancelada_em' => null,
            'renovacao_cancelada_em' => null, // pagou de novo: volta a renovar
        ])->save();

        return $a;
    }

    /**
     * Cancela a renovação: o ciclo pago continua até o fim, a fatura pendente
     * é cancelada e a rotina não gera outra.
     *
     * @throws \DomainException sem plano pago em vigor
     */
    public function cancelarRenovacao(Tenant|int $tenant): Assinatura
    {
        $a = $this->da($tenant);
        if ($a->situacao() !== 'ativa') {
            throw new \DomainException('Não há plano pago em vigor para cancelar.');
        }

        DB::transaction(function () use ($a) {
            $a->forceFill(['renovacao_cancelada_em' => Carbon::now()])->save();
            \App\Models\Fatura::where('tenant_id', $a->tenant_id)->where('status', 'pendente')
                ->update(['status' => 'cancelada', 'cancelada_em' => Carbon::now()]);
        });

        return $a;
    }

    /** @throws \DomainException nada para retomar */
    public function retomarRenovacao(Tenant|int $tenant): Assinatura
    {
        $a = $this->da($tenant);
        if ($a->situacao() !== 'ativa' || $a->renovacao_cancelada_em === null) {
            throw new \DomainException('Não há cancelamento agendado para desfazer.');
        }
        $a->forceFill(['renovacao_cancelada_em' => null])->save();

        return $a;
    }

    /** Cancelamento imediato (uso da equipe comercial, ex.: fraude). Cliente usa cancelarRenovacao(). */
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

    /** Convites ainda válidos: cada um reserva uma vaga no limite de usuários. */
    public function convitesPendentes(Tenant|int $tenant): int
    {
        $id = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return DB::table('convites')->where('tenant_id', $id)->whereNull('aceito_em')->whereNull('cancelado_em')->where('expira_em', '>', Carbon::now())->count();
    }

    /**
     * Recusa quando o plano não comporta mais `$acrescimo` do recurso.
     * Usuários: ativos + convites pendentes. Aceitar um convite passa
     * `$acrescimo = 0`, porque a vaga dele já está na conta.
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
        $convites = $recurso === 'usuarios' ? $this->convitesPendentes($tenant) : 0;
        if ($uso + $convites + $acrescimo <= $limite) {
            return;
        }

        $nome = $a->planoConfig()['nome'] ?? $a->plano;
        $rotulo = ['usuarios' => 'usuários ativos', 'funis' => 'funis', 'leads' => 'leads'][$recurso];
        $tem = $convites > 0 ? "{$uso} e {$convites} ".($convites === 1 ? 'convite pendente' : 'convites pendentes') : (string) $uso;

        throw new LimiteDoPlanoException($recurso, "O plano {$nome} permite até {$limite} {$rotulo} e você já tem {$tem}. Troque de plano em Configurações › Assinatura para continuar.");
    }

    /**
     * O que impede a empresa de contratar `$plano` hoje: uma frase por
     * recurso acima do limite. Vazio = cabe.
     *
     * @return list<string>
     */
    public function excessos(Tenant|int $tenant, string $plano): array
    {
        $config = collect(config('comercial.planos', []))->firstWhere('chave', $plano);
        if ($config === null) {
            return [];
        }

        $uso = $this->uso($tenant);
        $uso['usuarios'] += $this->convitesPendentes($tenant);
        $ondeReduzir = [
            'usuarios' => ['usuários ativos', 'Desative %d em Configurações › Equipe'],
            'funis' => ['funis', 'Arquive %d em Configurações › Funis'],
            'leads' => ['leads', 'Mova %d para a Lixeira'],
        ];

        $frases = [];
        foreach ($ondeReduzir as $recurso => [$rotulo, $acao]) {
            $limite = $config['limites'][$recurso] ?? null;
            if ($limite !== null && $uso[$recurso] > $limite) {
                $frases[] = "O plano {$config['nome']} permite até {$limite} {$rotulo} e a empresa tem {$uso[$recurso]}. ".sprintf($acao, $uso[$recurso] - $limite).' ou escolha outro plano.';
            }
        }

        return $frases;
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
            'carencia_ate' => $a->situacao() === 'carencia' ? $a->fimDaCarencia()->toDateString() : null,
            'somente_leitura_desde' => $a->somenteLeituraDesde()?->toDateString(),
            'suspende_em' => $a->suspendeEm()?->toDateString(),
            'suspensa' => $a->suspensa(),
            'renovacao_cancelada' => $a->renovacao_cancelada_em !== null,
            'limites' => $a->limites(),
            'uso' => $this->uso($tenant),
            'fatura_pendente' => \App\Models\Fatura::where('tenant_id', $a->tenant_id)->where('status', 'pendente')->latest('id')->first()?->paraOFront(),
        ];
    }

    public function planoExiste(string $chave): bool
    {
        return collect(config('comercial.planos', []))->contains('chave', $chave);
    }
}
