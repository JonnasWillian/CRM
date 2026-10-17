<?php

namespace Tests\Feature\Navegacao;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * As páginas recebem os dados iniciais por props do Inertia, não por uma
 * rajada de axios no mount. Cada página tem seu componente e suas props
 * obrigatórias; `agentes` só vai para quem tem leads.view-all.
 */
class PaginasComPropsTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_leads_index_entrega_grade_paginada_catalogos_e_agentes_para_o_gestor(): void
    {
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $this->lead($tenant, $gestor);

        $this->actingAs($gestor)->get('/leads?sort=nome&dir=asc&per_page=50')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Index')
            ->has('leads.data', 1)
            ->where('leads.meta.per_page', 50)
            ->where('leads.sort.por', 'nome')
            ->has('estagios')
            ->has('funis')
            ->has('agentes', 1));
    }

    public function test_vendedor_nao_recebe_agentes(): void
    {
        $tenant = $this->novoTenant();
        $vendedor = $this->agente($tenant);

        $this->actingAs($vendedor)->get('/leads')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Leads/Index')
            ->where('agentes', []));
    }

    public function test_dashboard_entrega_periodo_na_hora_e_metricas_e_tarefas_diferidas(): void
    {
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $this->lead($tenant, $gestor);

        // Primeira resposta: a casca (período, presets, funis, agentes); as props diferidas ficam para o segundo passo.
        $this->actingAs($gestor)->get('/dashboard?periodo=7d')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('periodo.periodo', '7d')
            ->has('presets', 5)
            ->has('funis')
            ->has('agentes', 1)
            ->missing('metricas')
            ->missing('tarefas'));

        // Segundo passo (o cliente pede só as diferidas). A versão só existe
        // depois que o middleware rodou na primeira resposta.
        $this->actingAs($gestor)->get('/dashboard?periodo=7d', [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data' => 'metricas,tarefas',
        ])->assertOk()
            ->assertJsonPath('component', 'Dashboard')
            ->assertJsonPath('props.metricas.periodo.preset', '7d')
            ->assertJsonPath('props.metricas.total_leads', 1)
            ->assertJsonStructure(['props' => ['metricas' => ['forecast'], 'tarefas' => ['hoje' => ['total'], 'atrasadas' => ['itens']]]]);
    }

    public function test_tarefas_index_entrega_lista_paginada(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        \App\Models\Tarefa::create(['usuario_id' => $this->lead($tenant, $dono)->id, 'titulo' => 'Ligar', 'data_limite' => today()]);

        $this->actingAs($dono)->get('/tarefas?escopo=hoje')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Tarefas/Index')
            ->has('tarefas.data', 1)
            ->where('tarefas.filtros.escopo', 'hoje')
            ->where('agentes', []));
    }

    public function test_rota_antiga_de_tarefas_pendentes_nao_existe(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);

        $this->actingAs($dono)->getJson('/api/tarefasPendentes')->assertNotFound();
    }
}
