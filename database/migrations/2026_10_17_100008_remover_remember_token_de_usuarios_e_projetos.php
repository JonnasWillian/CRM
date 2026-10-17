<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** `remember_token` só faz sentido em quem faz login; lead e projeto não fazem. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['usuarios', 'projetos'] as $tabela) {
            if (Schema::hasColumn($tabela, 'remember_token')) {
                Schema::table($tabela, fn (Blueprint $t) => $t->dropColumn('remember_token'));
            }
        }
    }

    public function down(): void
    {
        foreach (['usuarios', 'projetos'] as $tabela) {
            Schema::table($tabela, fn (Blueprint $t) => $t->rememberToken());
        }
    }
};
