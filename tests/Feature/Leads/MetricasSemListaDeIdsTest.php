<?php

namespace Tests\Feature\Leads;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Statu;
use App\Models\Tarefa;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class MetricasSemListaDeIdsTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;
    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->novoTenant();
        $this->vendedor = $this->agente($this->tenant);

        $funil = Funil::where('is_default', true)->first() ?? Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
        $aberto = Estagio::factory()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);
        $ganho = Estagio::factory()->ganho()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);
        $statusAberto = Statu::factory()->create(['tenant_id' => $this->tenant->id]);

        $a = $this->lead($this->tenant, $this->vendedor, ['estagio_id' => $aberto->id]);
        $this->lead($this->tenant, $this->vendedor, ['estagio_id' => $aberto->id]);
        $this->lead($this->tenant, $this->vendedor, ['estagio_id' => $ganho->id]);
        $this->projeto($a, ['preco' => 1000, 'status_id' => $statusAberto->id]);

        // Na lixeira: não conta em nada.
        $this->lead($this->tenant, $this->vendedor, ['estagio_id' => $aberto->id])->delete();
        // De um colega: o vendedor não enxerga.
        $this->lead($this->tenant, $this->agente($this->tenant), ['estagio_id' => $aberto->id]);

        Tarefa::create(['usuario_id' => $a->id, 'titulo' => 'Ligar hoje', 'data_limite' => today()]);
        Tarefa::create(['usuario_id' => $a->id, 'titulo' => 'Atrasada', 'data_limite' => today()->subDay()]);
    }

    public function test_numeros_das_metricas(): void
    {
        $this->actingAs($this->vendedor)->getJson('/api/metricas')->assertOk()
            ->assertJsonPath('total_leads', 3)
            ->assertJsonPath('leads_ativos', 2)
            ->assertJsonPath('leads_arquivados', 1)
            ->assertJsonPath('total_com_projeto', 1)
            // json_encode(1000.0) nesta versão do PHP (8.2, serialize_precision=-1)
            // vira o literal "1000" (sem ".0"), que json_decode volta como int.
            // assertJsonPath usa assertSame (estrito) — por isso 1000, não 1000.0.
            ->assertJsonPath('valor_projetos_abertos', 1000)
            ->assertJsonPath('taxa_conversao', 33.3);
    }

    public function test_tarefas_pendentes(): void
    {
        $this->actingAs($this->vendedor)->getJson('/api/tarefasPendentes')->assertOk()
            ->assertJsonCount(1, 'hoje')
            ->assertJsonCount(1, 'atrasadas');
    }

    public function test_nenhuma_consulta_carrega_a_carteira_como_lista_de_ids(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $this->lead($this->tenant, $this->vendedor);
        }

        $maiorNumeroDeBindings = 0;
        DB::listen(function ($q) use (&$maiorNumeroDeBindings) {
            $maiorNumeroDeBindings = max($maiorNumeroDeBindings, count($q->bindings));
        });

        $this->actingAs($this->vendedor)->getJson('/api/metricas')->assertOk();
        $this->actingAs($this->vendedor)->getJson('/api/tarefasPendentes')->assertOk();

        $this->assertLessThan(10, $maiorNumeroDeBindings, 'Alguma consulta recebeu a lista de ids dos leads como bindings.');
    }
}
