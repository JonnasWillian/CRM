<?php

namespace Tests\Feature\Tarefas;

use App\Models\Tarefa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/** Substitui /api/tarefasPendentes: contagens totais + os 10 primeiros de cada grupo. */
class ResumoDeTarefasTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_totais_e_top_10_por_grupo(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);

        for ($i = 1; $i <= 12; $i++) {
            Tarefa::create(['usuario_id' => $lead->id, 'titulo' => "Atrasada {$i}", 'data_limite' => today()->subDays($i)]);
        }
        Tarefa::create(['usuario_id' => $lead->id, 'titulo' => 'Hoje', 'data_limite' => today()]);
        Tarefa::create(['usuario_id' => $lead->id, 'titulo' => 'Amanhã', 'data_limite' => today()->addDay()]);
        Tarefa::create(['usuario_id' => $lead->id, 'titulo' => 'Em 10 dias', 'data_limite' => today()->addDays(10)]);
        Tarefa::create(['usuario_id' => $lead->id, 'titulo' => 'Feita', 'data_limite' => today(), 'concluido' => true, 'concluido_em' => now()]);

        $colega = $this->agente($tenant);
        Tarefa::create(['usuario_id' => $this->lead($tenant, $colega)->id, 'titulo' => 'Do colega', 'data_limite' => today()]);

        $r = $this->actingAs($dono)->getJson('/api/tarefas/resumo')->assertOk()->json();

        $this->assertSame(12, $r['atrasadas']['total']);
        $this->assertCount(10, $r['atrasadas']['itens']);
        $this->assertSame('Atrasada 12', $r['atrasadas']['itens'][0]['titulo'], 'mais antiga primeiro');
        $this->assertSame($lead->nome, $r['atrasadas']['itens'][0]['lead']['nome']);
        $this->assertSame(1, $r['hoje']['total']);
        $this->assertSame(1, $r['proximos_7_dias']['total']);
    }
}
