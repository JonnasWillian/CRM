<?php

namespace App\Observers;

use App\Models\Estagio;
use App\Models\Usuario;
use App\Support\ActivityLog\LeadActivity;
use App\Support\Reports\MetricasCache;
use Illuminate\Support\Facades\DB;

/**
 * Observa o próprio lead: criação, mudança de estágio e mudança de funil.
 *
 * Mantém também as colunas de evento do lead:
 * - `ultimo_contato_em`: toda mudança de estágio conta como contato (a
 *   anotação é tratada em AnotacaoObserver);
 * - `fechado_em`: preenchido ao entrar em estágio ganho/perdido, zerado ao
 *   voltar para aberto;
 * - `probabilidade`: preenchida pela sugestão do estágio só quando vazia —
 *   um valor que o vendedor digitou nunca é sobrescrito.
 *
 * As consultas de estágio usam DB::table de propósito: `saving` roda antes
 * do BelongsToTenant preencher tenant_id num lead novo, e o TenantScope é
 * fail-closed. O estagio_id já foi validado contra o tenant pelo request.
 */
class UsuarioObserver
{
    public function saving(Usuario $usuario): void
    {
        if (! $usuario->exists && $usuario->ultimo_contato_em === null) {
            $usuario->ultimo_contato_em = now();
        }

        if (! $usuario->isDirty('estagio_id')) {
            return;
        }

        if ($usuario->exists) {
            $usuario->ultimo_contato_em = now();
        }

        $estagio = $usuario->estagio_id
            ? DB::table('estagios')->where('id', $usuario->estagio_id)->first(['tipo', 'probabilidade_padrao'])
            : null;

        if ($estagio === null) {
            return;
        }

        if ($estagio->tipo === Estagio::TIPO_ABERTO) {
            $usuario->fechado_em = null;
            // A sugestão do estágio só preenche o que está vazio.
            if ($usuario->probabilidade === null && $estagio->probabilidade_padrao !== null) {
                $usuario->probabilidade = (int) $estagio->probabilidade_padrao;
            }

            return;
        }

        if ($usuario->fechado_em === null) {
            $usuario->fechado_em = now();
        }

        // Ganho é 100% e perdido é 0% por definição — o forecast só olha os
        // abertos, mas "60% de chance" num lead ganho confunde quem lê.
        $usuario->probabilidade = $estagio->tipo === Estagio::TIPO_GANHO ? 100 : 0;
    }

    public function created(Usuario $usuario): void
    {
        LeadActivity::registrar($usuario, $usuario->id, 'lead_criado', 'Lead cadastrado no sistema');
        MetricasCache::invalidar($usuario->tenant_id);
    }

    public function updated(Usuario $usuario): void
    {
        MetricasCache::invalidar($usuario->tenant_id);

        if ($usuario->wasChanged('user_id')) {
            $this->registrarTransferencia($usuario);
        }

        $mudouEstagio = $usuario->wasChanged('estagio_id');
        $mudouFunil = $usuario->wasChanged('funil_id');

        if (! $mudouEstagio && ! $mudouFunil) {
            return;
        }

        $estagioAnterior = $usuario->getOriginal('estagio_id');
        $funilAnterior = $usuario->getOriginal('funil_id');

        // Uma troca de funil é sempre também uma troca de estágio (o lead cai
        // num estágio do funil de destino): uma linha só no histórico, com o
        // evento rotulado conforme o caso.
        //
        // Nome gravado junto com o id: o histórico mostra o estágio como ele se
        // chamava no momento, mesmo que seja renomeado ou arquivado depois.
        // Uma consulta para os dois ids, não uma por id.
        $nomes = DB::table('estagios')
            ->whereIn('id', array_filter([$estagioAnterior, $usuario->estagio_id]))
            ->pluck('descricao', 'id');

        LeadActivity::registrar(
            $usuario,
            $usuario->id,
            $mudouFunil ? 'funil_alterado' : 'status_alterado',
            $mudouFunil ? 'Lead movido de funil' : 'Estágio do lead alterado',
            [
                'estagio_anterior_id' => $estagioAnterior,
                'estagio_novo_id' => $usuario->estagio_id,
                'funil_anterior_id' => $funilAnterior,
                'funil_novo_id' => $usuario->funil_id,
                'estagio_anterior' => $estagioAnterior ? $nomes[$estagioAnterior] ?? null : null,
                'estagio_novo' => $usuario->estagio_id ? $nomes[$usuario->estagio_id] ?? null : null,
            ],
        );
    }

    /** Nomes gravados junto: o histórico mostra quem era quem no momento. */
    private function registrarTransferencia(Usuario $usuario): void
    {
        $anterior = $usuario->getOriginal('user_id');
        $nomes = DB::table('users')->whereIn('id', array_filter([$anterior, $usuario->user_id]))->pluck('name', 'id');

        LeadActivity::registrar($usuario, $usuario->id, 'responsavel_alterado', 'Responsável alterado', [
            'responsavel_anterior_id' => $anterior,
            'responsavel_novo_id' => $usuario->user_id,
            'responsavel_anterior' => $anterior ? $nomes[$anterior] ?? null : null,
            'responsavel_novo' => $nomes[$usuario->user_id] ?? null,
        ]);
    }

    public function deleted(Usuario $usuario): void
    {
        MetricasCache::invalidar($usuario->tenant_id);

        // forceDelete também dispara `deleted`; com RESTRICT ele só passa em
        // lead sem filhos, e o log não deve chamá-lo de "lixeira".
        if ($usuario->isForceDeleting()) {
            return;
        }

        LeadActivity::registrar($usuario, $usuario->id, 'lead_removido', 'Lead movido para a lixeira');
    }

    public function restored(Usuario $usuario): void
    {
        MetricasCache::invalidar($usuario->tenant_id);
        LeadActivity::registrar($usuario, $usuario->id, 'lead_restaurado', 'Lead restaurado da lixeira');
    }
}
