<?php

namespace App\Services\Assinaturas;

use App\Mail\AvisoDeAssinaturaMail;
use App\Models\Assinatura;
use App\Models\Fatura;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Envia cada aviso de assinatura uma vez só por empresa, tipo e prazo
 * (tabela avisos_de_assinatura, índice único). Destinatários: admins ativos
 * da empresa; a equipe comercial (comercial.contato.email) recebe as faturas.
 */
class AvisosDeAssinatura
{
    /** Marca o evento como feito. false = já tinha sido feito antes. */
    public function registrar(int $tenantId, string $tipo, string $referencia): bool
    {
        return DB::table('avisos_de_assinatura')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'tipo' => $tipo,
            'referencia' => $referencia,
            'enviado_em' => Carbon::now(),
        ]) > 0;
    }

    /** @param  array<string, mixed>  $extra */
    public function avisar(Assinatura $a, string $tipo, string $referencia, array $extra = []): bool
    {
        if (! $this->registrar($a->tenant_id, $tipo, $referencia)) {
            return false;
        }

        $admins = $this->adminsDe($a->tenant_id);
        if ($admins->isNotEmpty()) {
            Mail::to($admins)->send(new AvisoDeAssinaturaMail($tipo, $this->dados($a) + $extra));
        }

        return true;
    }

    public function faturaGerada(Fatura $fatura): void
    {
        $a = Assinatura::where('tenant_id', $fatura->tenant_id)->firstOrFail();
        if (! $this->avisar($a, 'fatura_gerada', (string) $fatura->id, ['fatura' => $this->fatura($fatura)])) {
            return;
        }

        if ($comercial = config('comercial.contato.email')) {
            Mail::to($comercial)->send(new AvisoDeAssinaturaMail('comercial_fatura_gerada', $this->dados($a) + ['fatura' => $this->fatura($fatura)]));
        }
    }

    public function pagamentoConfirmado(Fatura $fatura): void
    {
        $a = Assinatura::where('tenant_id', $fatura->tenant_id)->firstOrFail();
        $this->avisar($a, 'pagamento_confirmado', (string) $fatura->id, ['fatura' => $this->fatura($fatura)]);
    }

    /** @return array<string, mixed> */
    public function fatura(Fatura $f): array
    {
        return [
            'id' => $f->id,
            'plano' => $f->planoNome(),
            'ciclo' => $f->ciclo,
            'usuarios' => $f->usuarios,
            'valor' => Fatura::emReais($f->valor),
            'vence_em' => $f->vence_em->format('d/m/Y'),
            'motivo' => $f->motivo,
        ];
    }

    /** @return array<string, mixed> */
    private function dados(Assinatura $a): array
    {
        $data = fn (?Carbon $c) => $c?->format('d/m/Y');

        return [
            'empresa' => Tenant::whereKey($a->tenant_id)->value('nome'),
            'plano' => $a->planoConfig()['nome'] ?? $a->plano,
            'teste_termina_em' => $data($a->teste_termina_em),
            'vigente_ate' => $data($a->vigente_ate),
            'carencia_ate' => $data($a->vigente_ate ? $a->fimDaCarencia() : null),
            'somente_leitura_desde' => $data($a->somenteLeituraDesde()),
            'suspende_em' => $data($a->suspendeEm()),
            'instrucoes' => config('comercial.pagamento.instrucoes'),
            'url' => route('configuracoes.assinatura'),
        ];
    }

    /** @return Collection<int, User> */
    private function adminsDe(int $tenantId): Collection
    {
        // Papéis são por empresa (teams do spatie): a consulta precisa do time certo.
        $anterior = getPermissionsTeamId();
        setPermissionsTeamId($tenantId);
        try {
            return User::role('admin')->where('tenant_id', $tenantId)->where('ativo', true)->get(['id', 'name', 'email']);
        } finally {
            setPermissionsTeamId($anterior);
        }
    }
}
