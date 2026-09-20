<?php

namespace App\Support\ActivityLog;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;

/**
 * Ponto único de escrita do activity log de lead.
 *
 * Os sete observers diferem só em duas coisas: como chegam ao lead a partir do
 * model observado, e que evento registram. Todo o resto — subject, causer,
 * lead_id, tenant_id — é igual, e mora aqui.
 *
 * O `tenant_id` é herdado do subject, não lido do tenant ativo. Dois motivos:
 * o evento não pode pertencer a um tenant diferente daquele da linha que ele
 * descreve, e escrever no log deixa de exigir um CurrentTenant setado — o que
 * importa no backfill, que roda no console e percorre vários tenants. A leitura
 * continua passando pelo TenantScope, fail-closed como o resto do domínio.
 *
 * O `causer` fica por conta do pacote, que resolve o usuário autenticado.
 */
class LeadActivity
{
    public static function registrar(
        Model $subject,
        ?int $leadId,
        string $event,
        string $descricao,
        array $propriedades = [],
    ): ?Activity {
        return activity()
            ->on($subject)
            ->event($event)
            ->withProperties($propriedades)
            ->tap(function (Activity $activity) use ($subject, $leadId) {
                $activity->lead_id = $leadId;
                $activity->tenant_id = $subject->tenant_id;
            })
            ->log($descricao);
    }
}
