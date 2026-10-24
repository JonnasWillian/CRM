<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Usuários como assentos comprados e troca de plano com crédito:
 * - assinaturas.assentos: quantos usuários o ciclo pago cobre (limite de
 *   usuários do plano pago); inicio_do_ciclo + valor_do_ciclo permitem
 *   calcular o crédito do que sobra numa troca para cima.
 * - faturas.credito: desconto do ciclo atual numa troca; valor = bruto − crédito.
 * Assinaturas pagas já existentes ficam com os usuários ativos de hoje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assinaturas', function (Blueprint $table) {
            $table->unsignedInteger('assentos')->nullable()->after('ciclo');
            $table->timestamp('inicio_do_ciclo')->nullable()->after('teste_termina_em');
            $table->decimal('valor_do_ciclo', 12, 2)->nullable()->after('vigente_ate');
        });
        Schema::table('faturas', function (Blueprint $table) {
            $table->decimal('credito', 12, 2)->default(0)->after('meses');
        });

        foreach (DB::table('assinaturas')->where('status', 'ativa')->whereNull('assentos')->get(['id', 'tenant_id']) as $a) {
            $ativos = DB::table('users')->where('tenant_id', $a->tenant_id)->where('ativo', true)->count();
            DB::table('assinaturas')->where('id', $a->id)->update(['assentos' => max(1, $ativos)]);
        }
    }

    public function down(): void
    {
        Schema::table('faturas', fn (Blueprint $table) => $table->dropColumn('credito'));
        Schema::table('assinaturas', fn (Blueprint $table) => $table->dropColumn(['assentos', 'inicio_do_ciclo', 'valor_do_ciclo']));
    }
};
