<?php

namespace Tests\Feature\Tarefas;

use App\Models\Tarefa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class ListagemGlobalDeTarefasTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_escopos_ordem_busca_e_visibilidade(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $gestor = $this->agente($tenant, 'gestor');
        $lead = $this->lead($tenant, $dono, ['nome' => 'Empresa Alfa Ltda']);

        $atrasada = Tarefa::create(['usuario_id' => $lead->id, 'titulo' => 'Ligar', 'data_limite' => today()->subDays(2)]);
        $hoje = Tarefa::create(['usuario_id' => $lead->id, 'titulo' => 'Reunião', 'data_limite' => today()]);
        $proxima = Tarefa::create(['usuario_id' => $lead->id, 'titulo' => 'Proposta', 'data_limite' => today()->addDays(3)]);
        $longe = Tarefa::create(['usuario_id' => $lead->id, 'titulo' => 'Follow-up', 'data_limite' => today()->addDays(30)]);
        $feita = Tarefa::create(['usuario_id' => $lead->id, 'titulo' => 'Enviar contrato', 'data_limite' => today(), 'concluido' => true, 'concluido_em' => now()]);
        $doColega = Tarefa::create(['usuario_id' => $this->lead($tenant, $this->agente($tenant))->id, 'titulo' => 'Ligar', 'data_limite' => today()]);

        $ids = fn ($r) => collect($r->json('data'))->pluck('id')->all();

        $this->assertSame([$atrasada->id], $ids($this->actingAs($dono)->getJson('/api/tarefas?escopo=atrasadas')->assertOk()));
        $this->assertSame([$hoje->id], $ids($this->actingAs($dono)->getJson('/api/tarefas?escopo=hoje')->assertOk()));
        $this->assertSame([$hoje->id, $proxima->id], $ids($this->actingAs($dono)->getJson('/api/tarefas?escopo=proximos_7')->assertOk()));
        $this->assertSame([$atrasada->id, $hoje->id, $proxima->id, $longe->id], $ids($this->actingAs($dono)->getJson('/api/tarefas?escopo=pendentes')->assertOk()), 'vencimento asc por padrão');
        $this->assertSame([$feita->id], $ids($this->actingAs($dono)->getJson('/api/tarefas?escopo=concluidas')->assertOk()));
        $this->assertSame([$longe->id, $proxima->id, $hoje->id, $atrasada->id], $ids($this->actingAs($dono)->getJson('/api/tarefas?escopo=pendentes&sort=data_limite&dir=desc')->assertOk()));

        $this->assertSame([$atrasada->id, $doColega->id], $ids($this->actingAs($gestor)->getJson('/api/tarefas?busca=ligar')->assertOk()));
        $this->assertSame([$doColega->id], $ids($this->actingAs($gestor)->getJson('/api/tarefas?busca=ligar&user_id='.$doColega->lead->user_id)->assertOk()));
        $this->assertSame([$hoje->id], $ids($this->actingAs($dono)->getJson('/api/tarefas?busca=alfa&escopo=proximos_7&page=1&per_page=1')->assertOk()->assertJsonPath('meta.total', 2)));

        $r = $this->actingAs($dono)->getJson('/api/tarefas?escopo=hoje')->json('data.0');
        $this->assertSame('Empresa Alfa Ltda', $r['lead']['nome']);
    }
}
