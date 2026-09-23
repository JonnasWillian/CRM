<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Liga o estágio ao seu funil e dá a ele um tipo semântico.
 *
 * `tipo` substitui `is_active` como âncora das métricas. Com estágios livres
 * por tenant, nenhum código pode inferir significado do nome — "Fechado",
 * "Ganhamos" e "Assinado" seriam todos o mesmo conceito e nenhum deles
 * reconhecível. O tipo é o que `leads_ativos`, `taxa_conversao` e afins passam
 * a ler. `is_active` é removido na migração de dados seguinte, para não sobrar
 * duas fontes de verdade discordando.
 *
 * `funil_id` nasce nullable porque as linhas existentes ainda não têm funil; a
 * migração de dados seguinte preenche e só então torna a coluna obrigatória.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estagios', function (Blueprint $table) {
            $table->unsignedBigInteger('funil_id')->nullable()->after('tenant_id');
            $table->index('funil_id', 'estagios_funil_id_index');
            $table->foreign('funil_id', 'estagios_funil_id_foreign')
                ->references('id')->on('funis')->restrictOnDelete();

            $table->enum('tipo', ['aberto', 'ganho', 'perdido'])->default('aberto')->after('ordem');
            $table->string('cor', 7)->nullable()->after('tipo');
            $table->index(['tenant_id', 'funil_id'], 'estagios_tenant_id_funil_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('estagios', function (Blueprint $table) {
            // FK primeiro: enquanto ela existe, o MySQL recusa remover qualquer
            // índice que possa sustentá-la.
            $table->dropForeign('estagios_funil_id_foreign');
            $table->dropIndex('estagios_funil_id_index');
            $table->dropIndex('estagios_tenant_id_funil_id_index');
            $table->dropColumn(['funil_id', 'tipo', 'cor']);
        });
    }
};
