<?php

use App\Services\Leads\NormalizacaoDeTelefones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Telefone deixa de ser número. BIGINT perdia o zero à esquerda e não
 * guardava "+", e o WhatsApp exige E.164.
 *
 * O MySQL converte o inteiro para a string de dígitos no ALTER; a conversão
 * para E.164 usa a mesma função que valida as entradas novas.
 *
 * Depende de código da aplicação (App\Services\Leads\NormalizacaoDeTelefones),
 * não de uma cópia congelada: um replay futuro desta migration (banco novo,
 * restauração de backup antigo) normaliza com as regras VIGENTES naquele
 * momento, que podem não ser as de hoje.
 *
 * down() é LOSSY: tira tudo que não é dígito para caber de volta em BIGINT, e
 * telefone vazio vira 0 (a coluna antiga era NOT NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('telefone', 20)->nullable()->change();
        });

        app(NormalizacaoDeTelefones::class)->executar(simular: false);
    }

    public function down(): void
    {
        DB::table('usuarios')->chunkById(500, function ($linhas) {
            foreach ($linhas as $linha) {
                DB::table('usuarios')->where('id', $linha->id)
                    ->update(['telefone' => preg_replace('/\D/', '', (string) $linha->telefone) ?: '0']);
            }
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->bigInteger('telefone')->change();
        });
    }
};
