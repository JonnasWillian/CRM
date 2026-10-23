<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Uma assinatura por empresa: teste de N dias ao nascer, depois um plano com
 * ciclo mensal ou anual. `status` é o que foi decidido (teste, ativa,
 * cancelada); vencimento é lido de `vigente_ate`/`teste_termina_em` na hora.
 * Empresas já existentes ganham um teste a partir de hoje.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assinaturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained('tenants')->restrictOnDelete();
            $table->string('plano', 30)->default('inicial');
            $table->string('ciclo', 10)->nullable(); // mensal | anual
            $table->string('status', 20)->default('teste'); // teste | ativa | cancelada
            $table->timestamp('teste_termina_em')->nullable();
            $table->timestamp('vigente_ate')->nullable();
            $table->timestamp('ativada_em')->nullable();
            $table->timestamp('cancelada_em')->nullable();
            $table->string('gateway', 30)->nullable(); // reservado para a cobrança (stripe, asaas…)
            $table->string('referencia_gateway')->nullable();
            $table->timestamps();
        });

        $dias = (int) config('comercial.dias_de_teste', 14);
        $agora = now();
        $linhas = DB::table('tenants')->whereNull('deleted_at')->pluck('id')
            ->map(fn ($id) => ['tenant_id' => $id, 'plano' => 'inicial', 'status' => 'teste', 'teste_termina_em' => $agora->copy()->addDays($dias), 'created_at' => $agora, 'updated_at' => $agora])
            ->all();
        if ($linhas !== []) {
            DB::table('assinaturas')->insert($linhas);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assinaturas');
    }
};
