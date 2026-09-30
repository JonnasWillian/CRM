<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VARCHAR(255) -> TEXT onde o usuário escreve livremente. Ver
 * App\Support\LimitesDeTexto. Nullable repetido de cada coluna original: o
 * ->change() do Laravel 11 descarta o que não for repetido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anotacaos', fn (Blueprint $t) => $t->text('descricao')->change());
        Schema::table('projetoAnotacaos', fn (Blueprint $t) => $t->text('descricao')->change());
        Schema::table('usuarios', fn (Blueprint $t) => $t->text('descricao')->nullable()->change());
        Schema::table('projetos', fn (Blueprint $t) => $t->text('descricao')->nullable()->change());
    }

    public function down(): void
    {
        // LOSSY se houver texto > 255: o MySQL em strict recusa o ALTER, e é
        // o comportamento desejado (não cortar texto do usuário em silêncio).
        Schema::table('anotacaos', fn (Blueprint $t) => $t->string('descricao')->change());
        Schema::table('projetoAnotacaos', fn (Blueprint $t) => $t->string('descricao')->change());
        Schema::table('usuarios', fn (Blueprint $t) => $t->string('descricao')->nullable()->change());
        Schema::table('projetos', fn (Blueprint $t) => $t->string('descricao')->nullable()->change());
    }
};
