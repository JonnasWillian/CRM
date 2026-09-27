<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Leva os estágios já existentes para a paleta validada.
 *
 * As cores semeadas antes desta entrega eram claras demais para fundo escuro —
 * reprovavam na banda de luminosidade do validador contra a superfície real. A
 * paleta nova está documentada em resources/css/theme.css.
 *
 * ── Só o que o tenant não escolheu ──
 *
 * A atualização casa pelo valor EXATO do default antigo. Uma cor que não
 * bate é uma cor que alguém escolheu na tela de configuração, e essa não se
 * mexe: o tenant decidiu, e sobrescrever a decisão dele para "padronizar"
 * seria trocar o dado do usuário por preferência nossa.
 */
return new class extends Migration
{
    private const DE_PARA = [
        '#60a5fa' => '#499fca',   // captação   → categórica 1
        '#ec4899' => '#e5618d',   // negociação → categórica 4
        '#f59e0b' => '#bd8939',   // desenvolv. → categórica 2
        '#34d399' => '#30ae77',   // concluído  → status ganho
        '#8b5cf6' => '#9268f3',   // pausado    → categórica 3
        '#ef4444' => '#f15873',   // cancelado  → status perdido
    ];

    public function up(): void
    {
        foreach (self::DE_PARA as $antiga => $nova) {
            DB::table('estagios')->where('cor', $antiga)->update(['cor' => $nova]);
        }
    }

    public function down(): void
    {
        foreach (self::DE_PARA as $antiga => $nova) {
            DB::table('estagios')->where('cor', $nova)->update(['cor' => $antiga]);
        }
    }
};
