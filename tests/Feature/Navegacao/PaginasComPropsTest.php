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

    public function test_paginas_de_configuracao_entregam_suas_listas(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $funil = \App\Models\Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        \App\Models\Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        \App\Models\MotivoPerda::factory()->create(['tenant_id' => $tenant->id]);
        $modelo = new \App\Models\TarefaPadrao(['user_id' => $admin->id, 'titulo' => 'Follow-up', 'prazo_dias' => 2]);
        $modelo->tenant_id = $tenant->id;
        $modelo->save();

        $this->actingAs($admin)->get('/configuracoes/funis')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Configuracoes/Funis')
            ->has('funis', 1)
            ->has('funis.0.estagios', 1)
            ->where('funis.0.is_default', true));

        $this->actingAs($admin)->get('/configuracoes/motivos-perda')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Configuracoes/MotivosPerda')
            ->has('motivos', 1)
            ->where('motivos.0.arquivado', false));

        $this->actingAs($admin)->get('/configuracoes/modelos-tarefa')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Configuracoes/ModelosTarefa')
            ->has('modelos', 1)
            ->where('modelos.0.titulo', 'Follow-up'));

        $this->actingAs($admin)->get('/modelos-tarefa')->assertNotFound();
    }

    public function test_relatorio_de_perdas_entrega_resumo_e_periodo(): void
    {
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');

        $this->actingAs($gestor)->get('/relatorios/perdas?periodo=30d&tipo=lead')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Relatorios/Perdas')
            ->where('relatorio.periodo.preset', '30d')
            ->where('relatorio.tipo', 'lead')
            ->has('relatorio.por_motivo')
            ->where('periodo.periodo', '30d')
            ->where('periodo.tipo', 'lead'));

        $this->actingAs($this->agente($tenant))->get('/relatorios/perdas')->assertForbidden();
    }
}
