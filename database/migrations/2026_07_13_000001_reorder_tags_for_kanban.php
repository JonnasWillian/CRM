<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Nova ordem: Captação → Negociação → Desenvolvimento → Concluído → Pausado → Cancelado
        DB::table('tags')->where('id', 1)->update(['ordem' => 1]); // Captação
        DB::table('tags')->where('id', 6)->update(['ordem' => 2]); // Negociação
        DB::table('tags')->where('id', 2)->update(['ordem' => 3]); // Desenvolvimento
        DB::table('tags')->where('id', 3)->update(['ordem' => 4]); // Concluído
        DB::table('tags')->where('id', 5)->update(['ordem' => 5]); // Pausado
        DB::table('tags')->where('id', 4)->update(['ordem' => 6]); // Cancelado
    }

    public function down(): void
    {
        DB::table('tags')->where('id', 1)->update(['ordem' => 1]);
        DB::table('tags')->where('id', 2)->update(['ordem' => 2]);
        DB::table('tags')->where('id', 3)->update(['ordem' => 3]);
        DB::table('tags')->where('id', 4)->update(['ordem' => 4]);
        DB::table('tags')->where('id', 5)->update(['ordem' => 5]);
        DB::table('tags')->where('id', 6)->update(['ordem' => 6]);
    }
};
