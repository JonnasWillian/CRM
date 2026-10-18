<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * O histórico de estágio passa a viver só no activity log (subject = lead).
 *
 * 1. Eventos antigos com subject `estagio_historico` (gravados pelo backfill)
 *    viram eventos do lead, com ids e nomes dos estágios nas properties.
 * 2. Linha de estagio_historicos sem evento correspondente (mesmo lead,
 *    mesmo instante) ganha um — o backfill pode não ter rodado.
 *
 * Idempotente: rodar de novo não duplica. Sem tenant scope (DB::table).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('estagio_historicos')) {
            return;
        }

        $nomes = fn (array $ids) => DB::table('estagios')->whereIn('id', array_filter($ids))->pluck('descricao', 'id');

        DB::table('estagio_historicos')->orderBy('id')->chunkById(500, function ($historicos) use ($nomes) {
            $mapa = $nomes($historicos->flatMap(fn ($h) => [$h->estagio_anterior_id, $h->estagio_novo_id])->unique()->all());

            foreach ($historicos as $h) {
                $mudouFunil = $h->funil_anterior_id !== null && $h->funil_novo_id !== null && $h->funil_anterior_id != $h->funil_novo_id;
                $props = [
                    'estagio_anterior_id' => $h->estagio_anterior_id,
                    'estagio_novo_id' => $h->estagio_novo_id,
                    'funil_anterior_id' => $h->funil_anterior_id,
                    'funil_novo_id' => $h->funil_novo_id,
                    'estagio_anterior' => $mapa[$h->estagio_anterior_id] ?? null,
                    'estagio_novo' => $mapa[$h->estagio_novo_id] ?? null,
                ];

                $existente = DB::table('activity_log')->where('subject_type', 'estagio_historico')->where('subject_id', $h->id)->first();
                if ($existente) {
                    $atuais = json_decode($existente->properties ?: '[]', true) ?: [];
                    DB::table('activity_log')->where('id', $existente->id)->update([
                        'subject_type' => 'lead',
                        'subject_id' => $h->usuario_id,
                        'lead_id' => $h->usuario_id,
                        'properties' => json_encode([...$props, ...array_filter($atuais, fn ($v) => $v !== null)]),
                    ]);

                    continue;
                }

                $jaTem = DB::table('activity_log')
                    ->where('lead_id', $h->usuario_id)
                    ->whereIn('event', ['status_alterado', 'funil_alterado'])
                    ->where('created_at', $h->created_at)
                    ->exists();
                if ($jaTem) {
                    continue;
                }

                DB::table('activity_log')->insert([
                    'log_name' => 'default',
                    'description' => $mudouFunil ? 'Lead movido de funil' : 'Estágio do lead alterado',
                    'subject_type' => 'lead',
                    'subject_id' => $h->usuario_id,
                    'event' => $mudouFunil ? 'funil_alterado' : 'status_alterado',
                    'properties' => json_encode($props),
                    'tenant_id' => $h->tenant_id,
                    'lead_id' => $h->usuario_id,
                    'created_at' => $h->created_at,
                    'updated_at' => $h->created_at,
                ]);
            }
        });
    }

    /** Sem volta: a origem continua em estagio_historicos até a migração seguinte. */
    public function down(): void {}
};
