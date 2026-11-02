<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Cobrança pelo Asaas (ver docs/comercial/plano-de-cobranca-e-assinaturas.md).
 *
 * - tenants: CPF/CNPJ de quem paga (o Asaas exige para cadastrar o cliente)
 *   e o id do cliente lá, criado uma vez por empresa.
 * - faturas: a cobrança no Asaas e o link da página de pagamento. Separado
 *   de `referencia_gateway`, que guarda quem confirmou o pagamento.
 * - eventos_de_gateway: cada aviso recebido pelo webhook, uma vez só
 *   (o Asaas entrega "pelo menos uma vez"), com o que foi feito dele.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('documento', 14)->nullable()->after('nome'); // só dígitos
            $table->string('asaas_cliente_id', 40)->nullable()->unique()->after('documento');
        });

        Schema::table('faturas', function (Blueprint $table) {
            $table->string('cobranca_id', 40)->nullable()->unique()->after('referencia_gateway');
            $table->string('link_pagamento')->nullable()->after('cobranca_id');
        });

        Schema::create('eventos_de_gateway', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30);
            $table->string('evento_id', 100);
            $table->string('tipo', 60);
            $table->string('cobranca_id', 40)->nullable()->index();
            $table->json('payload');
            $table->timestamp('processado_em')->nullable();
            $table->string('resultado')->nullable();
            $table->timestamps();
            $table->unique(['gateway', 'evento_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_de_gateway');
        Schema::table('faturas', fn (Blueprint $table) => $table->dropColumn(['cobranca_id', 'link_pagamento']));
        Schema::table('tenants', fn (Blueprint $table) => $table->dropColumn(['documento', 'asaas_cliente_id']));
    }
};
