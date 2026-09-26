<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cada perda é um evento, não um estado.
 *
 * Um lead pode ser perdido, reaberto e perdido de novo; um projeto também. Se o
 * motivo morasse numa coluna da entidade, a reabertura ou apagaria o dado ou o
 * deixaria mentindo, e o relatório só saberia responder "por que os atualmente
 * perdidos foram perdidos" — nunca "por que perdemos em agosto".
 *
 * `valor` é fotografia, não referência. O que se perdeu é quanto valia no
 * momento da perda: o preço do projeto naquele dia, a soma dos projetos abertos
 * daquele lead naquele dia. Calcular no relatório, a partir da entidade, daria
 * o valor de hoje — que mudou justamente porque o negócio foi perdido.
 *
 * Só `created_at`: evento não é editado. `user_id` é nullable porque a perda
 * sobrevive ao desligamento de quem a registrou.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perdas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');

            // Morph: `perdivel` é o que foi perdido — hoje Usuario (lead) ou
            // Projeto. O sufixo segue a convenção "-able" do Laravel, para que
            // a relação morphTo() se chame perdivel() sem configuração extra.
            $table->string('perdivel_type');
            $table->unsignedBigInteger('perdivel_id');

            $table->unsignedBigInteger('motivo_perda_id');
            $table->string('observacao', 500)->nullable();
            $table->decimal('valor', 15, 2)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('tenant_id', 'perdas_tenant_id_index');
            $table->foreign('tenant_id', 'perdas_tenant_id_foreign')
                ->references('id')->on('tenants')->restrictOnDelete();

            // restrict: apagar fisicamente um motivo em uso reescreveria a
            // história das perdas que o citam. Arquivar (soft delete) é o
            // caminho, e continua resolvendo o nome via withTrashed.
            $table->index('motivo_perda_id', 'perdas_motivo_perda_id_index');
            $table->foreign('motivo_perda_id', 'perdas_motivo_perda_id_foreign')
                ->references('id')->on('motivos_perda')->restrictOnDelete();

            $table->index('user_id', 'perdas_user_id_index');
            $table->foreign('user_id', 'perdas_user_id_foreign')
                ->references('id')->on('users')->nullOnDelete();

            // O relatório filtra por período e agrupa por motivo.
            $table->index(['tenant_id', 'created_at'], 'perdas_tenant_id_created_at_index');
            $table->index(['tenant_id', 'motivo_perda_id'], 'perdas_tenant_id_motivo_index');

            // "Quais perdas deste lead/projeto" — a timeline da entidade.
            $table->index(['perdivel_type', 'perdivel_id'], 'perdas_perdivel_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perdas');
    }
};
