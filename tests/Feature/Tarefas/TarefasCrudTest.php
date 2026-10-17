<?php

namespace Tests\Feature\Tarefas;

use App\Models\Tarefa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class TarefasCrudTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_cria_edita_conclui_reabre_e_exclui(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);

        $id = $this->actingAs($dono)->postJson("/api/leads/{$lead->id}/tarefas", ['titulo' => 'Ligar', 'data_limite' => today()->toDateString()])
            ->assertCreated()->json('data.id');

        $this->actingAs($dono)->postJson("/api/leads/{$lead->id}/tarefas", ['titulo' => '', 'data_limite' => 'ontem'])
            ->assertUnprocessable()->assertJsonValidationErrors(['titulo', 'data_limite']);

        $this->actingAs($dono)->putJson("/api/tarefas/{$id}", ['titulo' => 'Ligar amanhã', 'data_limite' => today()->addDay()->toDateString()])->assertOk();
        $this->actingAs($dono)->putJson("/api/tarefas/{$id}", ['concluido' => true])->assertOk()->assertJsonPath('data.concluido', true);
        $this->assertNotNull(Tarefa::find($id)->concluido_em);
        $this->actingAs($dono)->putJson("/api/tarefas/{$id}", ['concluido' => false])->assertOk();
        $this->assertNull(Tarefa::find($id)->concluido_em);

        $this->actingAs($dono)->deleteJson("/api/tarefas/{$id}")->assertOk();
        $this->assertSoftDeleted('tarefas', ['id' => $id]);
    }

    public function test_concluir_e_excluir_em_massa_so_mexem_no_que_e_meu(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);
        $doColega = $this->lead($tenant, $this->agente($tenant));
        $a = Tarefa::create(['usuario_id' => $lead->id, 'titulo' => 'A', 'data_limite' => today()]);
        $b = Tarefa::create(['usuario_id' => $lead->id, 'titulo' => 'B', 'data_limite' => today()]);
        $alheia = Tarefa::create(['usuario_id' => $doColega->id, 'titulo' => 'C', 'data_limite' => today()]);

        $this->actingAs($dono)->postJson('/api/tarefas/concluir', ['ids' => [$a->id, $alheia->id]])
            ->assertOk()->assertJsonPath('afetados', 1)->assertJsonPath('ignorados', 1);
        $this->assertTrue($a->fresh()->concluido);
        $this->assertFalse($alheia->fresh()->concluido);

        $this->actingAs($dono)->postJson('/api/tarefas/excluir', ['ids' => [$b->id, $alheia->id]])
            ->assertOk()->assertJsonPath('afetados', 1)->assertJsonPath('ignorados', 1);
        $this->assertSoftDeleted('tarefas', ['id' => $b->id]);
        $this->assertNotSoftDeleted('tarefas', ['id' => $alheia->id]);
    }
}
