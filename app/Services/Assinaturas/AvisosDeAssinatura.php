<?php

namespace App\Services\Assinaturas;

use App\Mail\AvisoDeAssinaturaMail;
use App\Models\Assinatura;
use App\Models\Fatura;
use App\Models\PerfilDeEmpresa;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

        try {
            $admins = $this->adminsDe($a->tenant_id);
            if ($admins->isNotEmpty()) {
                Mail::to($admins)->send(new AvisoDeAssinaturaMail($tipo, $this->dados($a) + $extra));
            }
        } catch (\Throwable $e) {
            // Não enviado não conta como enviado: a próxima rodada tenta de novo.
            DB::table('avisos_de_assinatura')->where(['tenant_id' => $a->tenant_id, 'tipo' => $tipo, 'referencia' => $referencia])->delete();

            throw $e;
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

    /**
     * Algo no pagamento pede decisão de gente (estorno, valor que não
     * confere, fatura cancelada paga). Vai para o log e, se configurado,
     * para o e-mail comercial. `unicoPor`: alerta que pode se repetir (a
     * tela e a conciliação conferem a mesma cobrança várias vezes) sai uma
     * vez só por essa chave.
     */
    public function alertaAoComercial(Fatura $f, string $titulo, string $mensagem, ?string $unicoPor = null): void
    {
        if ($unicoPor !== null && ! $this->registrar($f->tenant_id, 'alerta_pagamento', mb_substr($unicoPor, 0, 40))) {
            return;
        }
        Log::warning("Pagamento: {$titulo} (fatura #{$f->id}). {$mensagem}");

        if ($comercial = config('comercial.contato.email')) {
            Mail::to($comercial)->send(new AvisoDeAssinaturaMail('comercial_alerta', [
                'empresa' => Tenant::whereKey($f->tenant_id)->value('nome'),
                'fatura' => $this->fatura($f),
                'titulo' => $titulo,
                'mensagem' => $mensagem,
            ]));
        }
    }

    /**
     * Questionário de primeiro acesso respondido: as respostas, o celular e
     * o aceite de dicas vão para o e-mail comercial (o dono do produto quer
     * saber quem está entrando). Pular não avisa.
     */
    public function perfilRespondido(PerfilDeEmpresa $p): void
    {
        $comercial = config('comercial.contato.email');
        if (! $comercial || $p->respostas === null) {
            return;
        }

        $r = $p->respostas;
        $rotulo = fn (string $grupo, ?string $chave) => $chave === null ? 'não informado'
            : (data_get(config("onboarding.{$grupo}"), "{$chave}.rotulo") ?? config("onboarding.{$grupo}.{$chave}") ?? $chave);
        $lista = fn (string $grupo, array $chaves) => implode(', ', array_map(fn ($k) => $rotulo($grupo, $k), $chaves));
        $a = Assinatura::where('tenant_id', $p->tenant_id)->first();

        Mail::to($comercial)->send(new AvisoDeAssinaturaMail('comercial_perfil', [
            'empresa' => Tenant::whereKey($p->tenant_id)->value('nome'),
            'plano' => $a ? ($a->planoConfig()['nome'] ?? $a->plano ?? '') : '',
            'linhas' => [
                'Experiência com CRM: '.$rotulo('experiencias', $r['experiencia'] ?? null),
                'Cargo: '.$rotulo('cargos', $r['cargo'] ?? null),
                'Ramo: '.$rotulo('ramos', $r['ramo'] ?? null),
                'Pessoas que vão usar: '.$rotulo('tamanhos', $r['tamanho'] ?? null),
                'Vende para: '.$rotulo('para_quem', $r['para_quem'] ?? null),
                'Fala com os clientes por: '.$lista('canais', $r['canais'] ?? []),
                'Os leads vêm de: '.$lista('origens', $r['origens'] ?? []),
                'Como conheceu: '.$rotulo('conheceu', $r['conheceu'] ?? null),
                'Celular/WhatsApp: '.($p->telefone ?: 'não informado'),
                'Quer receber dicas pelo WhatsApp: '.($p->aceita_dicas_whatsapp ? 'sim' : 'não'),
            ],
        ]));
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
            'pagamento_online' => app(\App\Services\Pagamentos\Asaas\ApiDoAsaas::class)->ativo(),
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
