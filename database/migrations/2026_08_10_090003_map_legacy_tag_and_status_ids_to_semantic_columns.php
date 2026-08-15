<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // "Concluído", "Cancelado", "Pausado" — grupo hoje tratado como arquivado (whereIn tag_id [3,4,5])
        DB::table('tags')->whereIn('id', [3, 4, 5])->update(['is_active' => false]);
        // "Em captação", "Em desenvolvimento", "Em negociação" (ids 1,2,6) já ficam is_active=true pelo default da coluna — nada a fazer.

        // "Concluido" — status "ganho" (id 5, hoje comparado como where('status_id', 5))
        DB::table('status')->where('id', 5)->update(['is_won' => true]);
        // "Cancelado" — status "perdido" (id 6, hoje parte do whereNotIn('status_id', [5,6]))
        DB::table('status')->where('id', 6)->update(['is_lost' => true]);
        // "Em disputa" (id 7) fica is_won=false/is_lost=false pelo default — continua contando como "aberto", igual ao comportamento atual (não estava em [5,6]).
    }

    public function down(): void
    {
        //
    }
};
