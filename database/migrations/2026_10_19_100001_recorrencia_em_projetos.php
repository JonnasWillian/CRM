<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Projeto recorrente: valor mensal × prazo em meses, a partir de data_inicial.
 * Renovações e aditivos ficam em projeto_aditivos, em sequência, cada um com
 * os meses que acrescenta e, opcionalmente, o novo valor mensal.
 * cancelado_em: quando o projeto entrou num status perdido (a contagem para ali).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projetos', function (Blueprint $table) {
            $table->string('tipo', 20)->default('unico')->after('descricao');
            $table->decimal('valor_mensal', 12, 2)->nullable()->after('preco');
            $table->unsignedSmallInteger('duracao_meses')->nullable()->after('valor_mensal');
            $table->timestamp('cancelado_em')->nullable()->after('fechado_em');
            $table->index(['tenant_id', 'tipo'], 'projetos_tenant_tipo_idx');
        });

        Schema::create('projeto_aditivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('projeto_id')->constrained('projetos')->restrictOnDelete();
            $table->string('tipo', 20); // renovacao | aditivo
            $table->unsignedSmallInteger('meses');
            $table->decimal('valor_mensal', 12, 2)->nullable();
            $table->string('observacao', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['projeto_id', 'id'], 'projeto_aditivos_projeto_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projeto_aditivos');
        Schema::table('projetos', function (Blueprint $table) {
            $table->dropIndex('projetos_tenant_tipo_idx');
            $table->dropColumn(['tipo', 'valor_mensal', 'duracao_meses', 'cancelado_em']);
        });
    }
};
