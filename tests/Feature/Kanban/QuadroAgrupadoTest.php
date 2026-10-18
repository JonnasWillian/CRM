<?php

namespace Tests\Feature\Kanban;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\StatusProjeto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * O quadro vem agrupado do banco: por coluna, total, Σ valor estimado, Σ
 * ponderado e os N primeiros cards; "carregar mais" é uma página só daquela
 * coluna. O número de consultas é limitado, não cresce com os leads.
 */
class QuadroAgrupadoTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private function cenario(): array
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $a = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 1, 'probabilidade_padrao' => null]);
        $b = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 2]);
        $ganho = Estagio::factory()->ganho()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 3]);

        return compact('tenant', 'dono', 'funil', 'a', 'b', 'ganho');
    }

    public function test_totais_por_coluna_batem_com_o_calculo_em_php(): void
    {
        ['tenant' => $tenant, 'dono' => $dono, 'a' => $a, 'b' => $b, 'ganho' => $ganho] = $this->cenario();

        $this->lead($tenant, $dono, ['estagio_id' => $a->id, 'valor_estimado' => 1000, 'probabilidade' => 50]);
        $this->lead($tenant, $dono, ['estagio_id' => $a->id, 'valor_estimado' => 500, 'probabilidade' => null]);
        $this->lead($tenant, $dono, ['estagio_id' => $a->id, 'valor_estimado' => null, 'probabilidade' => 80]);
        $comProjeto = $this->lead($tenant, $dono, ['estagio_id' => $b->id, 'valor_estimado' => 2000, 'probabilidade' => 25]);
        $this->projeto($comProjeto, ['preco' => 300, 'status_id' => StatusProjeto::factory()->create(['tenant_id' => $tenant->id])->id]);
        $this->lead($tenant, $dono, ['estagio_id' => $ganho->id, 'valor_estimado' => 9000, 'valor_fechado' => 8000]);

        $dados = $this->actingAs($dono)->getJson('/api/kanban')->assertOk()->json();

        $colA = collect($dados['estagios'])->firstWhere('id', $a->id);
        $this->assertSame(3, $colA['total']);
        $this->assertEquals(1500.0, $colA['valor_estimado']);
        $this->assertEquals(500.0, $colA['valor_ponderado']); // só 1000×50%; 500 sem probabilidade não pondera
        $this->assertSame(1, $colA['sem_probabilidade']);
        $this->assertCount(3, $colA['leads']);
        $this->assertNull($colA['proxima_pagina']);

        $colB = collect($dados['estagios'])->firstWhere('id', $b->id);
        $this->assertEquals(300.0, $colB['valor_projetos']);
        $this->assertEquals(300.0, $colB['leads'][0]['valor_projetos']);

        $this->assertSame(5, $dados['totais']['leads']);
        $this->assertEquals(3500.0, $dados['totais']['valor_estimado_aberto']);
        $this->assertSame($dados['funil']['id'], $colA['funil_id'] ?? $dados['funil']['id']);
    }

    public function test_coluna_e_paginada_e_carregar_mais_devolve_so_aquela_coluna(): void
    {
        ['tenant' => $tenant, 'dono' => $dono, 'a' => $a] = $this->cenario();
        config(['leads.kanban.cards_por_coluna' => 2]);
        for ($i = 0; $i < 5; $i++) {
            $this->lead($tenant, $dono, ['estagio_id' => $a->id]);
        }

        $dados = $this->actingAs($dono)->getJson('/api/kanban')->assertOk()->json();
        $colA = collect($dados['estagios'])->firstWhere('id', $a->id);
        $this->assertSame(5, $colA['total']);
        $this->assertCount(2, $colA['leads']);
        $this->assertSame(2, $colA['proxima_pagina']);

        $pagina2 = $this->actingAs($dono)->getJson("/api/kanban/colunas/{$a->id}?page=2")->assertOk()->json();
        $this->assertCount(2, $pagina2['data']);
        $this->assertSame(2, $pagina2['meta']['current_page']);
        $this->assertSame(5, $pagina2['meta']['total']);
        $this->assertNotEquals(array_column($colA['leads'], 'id'), array_column($pagina2['data'], 'id'));
    }

    public function test_filtros_busca_responsavel_e_estagnados(): void
    {
        ['tenant' => $tenant, 'dono' => $dono, 'a' => $a] = $this->cenario();
        $colega = $this->agente($tenant);
        $gestor = $this->agente($tenant, 'gestor');
        $ana = $this->lead($tenant, $dono, ['estagio_id' => $a->id, 'nome' => 'Ana Parada']);
        $ana->forceFill(['ultimo_contato_em' => now()->subDays(30)])->saveQuietly();
        $this->lead($tenant, $colega, ['estagio_id' => $a->id, 'nome' => 'Beto Ativo']);

        $busca = $this->actingAs($gestor)->getJson('/api/kanban?q=parada')->assertOk()->json();
        $this->assertSame(['Ana Parada'], array_column(collect($busca['estagios'])->firstWhere('id', $a->id)['leads'], 'nome'));

        $porAgente = $this->actingAs($gestor)->getJson("/api/kanban?user_id={$colega->id}")->assertOk()->json();
        $this->assertSame(['Beto Ativo'], array_column(collect($porAgente['estagios'])->firstWhere('id', $a->id)['leads'], 'nome'));

        $parados = $this->actingAs($gestor)->getJson('/api/kanban?estagnados=1')->assertOk()->json();
        $coluna = collect($parados['estagios'])->firstWhere('id', $a->id);
        $this->assertSame(1, $coluna['total']);
        $this->assertTrue($coluna['leads'][0]['estagnado']);

        // Vendedor não filtra por outro responsável: o parâmetro é ignorado.
        $doDono = $this->actingAs($dono)->getJson("/api/kanban?user_id={$colega->id}")->assertOk()->json();
        $this->assertSame(['Ana Parada'], array_column(collect($doDono['estagios'])->firstWhere('id', $a->id)['leads'], 'nome'));
    }

    public function test_numero_de_consultas_nao_cresce_com_os_leads(): void
    {
        ['tenant' => $tenant, 'dono' => $dono, 'a' => $a, 'b' => $b] = $this->cenario();
        for ($i = 0; $i < 40; $i++) {
            $this->lead($tenant, $dono, ['estagio_id' => $i % 2 ? $a->id : $b->id]);
        }

        $consultas = 0;
        DB::listen(function () use (&$consultas) {
            $consultas++;
        });
        $dados = $this->actingAs($dono)->getJson('/api/kanban')->assertOk()->json();

        // Custo fixo (sessão, tenant, permissões, funis, agregados, projetos, estágios) + 1 por coluna; nunca 1 por lead.
        $colunas = count($dados['estagios']);
        $this->assertLessThanOrEqual(12 + $colunas, $consultas, "{$consultas} consultas para {$colunas} colunas");
    }

    public function test_funil_inexistente_e_404_e_sem_funil_padrao_devolve_vazio(): void
    {
        ['dono' => $dono] = $this->cenario();
        $this->actingAs($dono)->getJson('/api/kanban?funil_id=999999')->assertNotFound();

        Funil::query()->update(['is_default' => false]);
        Funil::query()->delete();
        $dados = $this->actingAs($dono)->getJson('/api/kanban')->assertOk()->json();
        $this->assertNull($dados['funil']);
        $this->assertSame([], $dados['estagios']);
    }

    public function test_pagina_do_pipeline_entrega_funis_e_agentes(): void
    {
        ['dono' => $dono, 'funil' => $funil] = $this->cenario();

        $this->actingAs($dono)->get('/kanban')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Kanban')
            ->where('funilAtual.id', $funil->id)
            ->has('funis', 1)
            ->has('estagios', 3)
            ->where('agentes', []));
    }
}
