<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarefas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('anotacao')->nullable();
            $table->date('data_limite');
            $table->boolean('concluido')->default(false);
            $table->timestamp('concluido_em')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['usuario_id', 'data_limite']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarefas');
    }
};
