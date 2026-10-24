<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Uma fatura por contratação ou renovação. O valor é congelado na geração
 * (usuários ativos × preço por usuário × meses); só o pagamento confirmado
 * ativa o plano. `gateway`/`referencia_gateway` guardam quem confirmou:
 * "manual" + a referência digitada no comando, até existir um gateway.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->string('plano', 30);
            $table->string('ciclo', 10); // mensal | anual
            $table->string('motivo', 20); // contratacao | renovacao | manual
            $table->unsignedInteger('usuarios');
            $table->decimal('preco_unitario', 10, 2); // por usuário por mês
            $table->unsignedTinyInteger('meses');
            $table->decimal('valor', 12, 2);
            $table->string('status', 20)->default('pendente'); // pendente | paga | cancelada
            $table->date('vence_em');
            $table->timestamp('paga_em')->nullable();
            $table->timestamp('cancelada_em')->nullable();
            $table->string('gateway', 30)->nullable();
            $table->string('referencia_gateway')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'vence_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faturas');
    }
};
