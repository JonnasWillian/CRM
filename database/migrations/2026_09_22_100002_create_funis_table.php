<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Funil de leads. Cada tenant tem um ou mais; cada funil tem seus estágios.
 *
 * `is_default` marca o funil em que um lead cai quando ninguém escolhe um.
 * A unicidade de "só um default por tenant" não é expressável como UNIQUE no
 * MySQL (haveria N linhas com is_default = false, e UNIQUE(tenant_id,
 * is_default) proibiria isso), então a garantia fica no FunilService, que
 * desmarca o anterior dentro da mesma transação.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('funis', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('nome');
            $table->string('descricao')->nullable();
            $table->unsignedBigInteger('ordem')->default(1);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id', 'funis_tenant_id_index');
            $table->foreign('tenant_id', 'funis_tenant_id_foreign')
                ->references('id')->on('tenants')->restrictOnDelete();

            $table->index(['tenant_id', 'ordem'], 'funis_tenant_id_ordem_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funis');
    }
};
