<?php

namespace Tests\Feature\Relatorios;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\MotivoPerda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/** Relatórios › Visão geral: tiles do período, tabela por vendedor (uma consulta agrupada) e ganhos por mês. */
class VisaoGeralTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private function cenario(): array
    {
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $ana = $this->agente($tenant);
        $bia = $this->agente($tenant);
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $aberto = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 1]);
        $ganho = Estagio::factory()->ganho()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 2]);
        $perdido = Estagio::factory()->perdido()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 3]);
        $motivo = MotivoPerda::factory()->create(['tenant_id' => $tenant->id]);

        // Ana: 2 abertos (um com valor ponderado), 1 ganho de 5.000.
        $this->lead($tenant, $ana, ['estagio_id' => $aberto->id, 'valor_estimado' => 1000, 'probabilidade' => 50]);
        $this->lead($tenant, $ana, ['estagio_id' => $aberto->id]);
        $ganhou = $this->lead($tenant, $ana, ['estagio_id' => $aberto->id, 'valor_estimado' => 5000]);
        $this->actingAs($ana)->patchJson("/api/leads/{$ganhou->id}/estagio", ['estagio_id' => $ganho->id])->assertOk();
        // Bia: 1 perdido.
        $perdeu = $this->lead($tenant, $bia, ['estagio_id' => $aberto->id]);
        $this->actingAs($bia)->patchJson("/api/leads/{$perdeu->id}/estagio", ['estagio_id' => $perdido->id, 'perda' => ['motivo_perda_id' => $motivo->id]])->assertOk();

        return compact('tenant', 'gestor', 'ana', 'bia');
    }

    public function test_tabela_por_vendedor_e_tiles(): void
    {
        ['gestor' => $gestor, 'ana' => $ana, 'bia' => $bia] = $this->cenario();

        $consultas = 0;
        DB::listen(function () use (&$consultas) {
            $consultas++;
        });
        $dados = $this->actingAs($gestor)->getJson('/api/relatorios/visao-geral?periodo=30d')->assertOk()->json();
        $this->assertLessThan(30, $consultas);

        $this->assertSame(4, $dados['metricas']['fluxo']['leads_criados']);
        $this->assertSame(1, $dados['metricas']['fluxo']['ganhos']['total']);
        $this->assertEquals(50.0, $dados['metricas']['fluxo']['taxa_ganho']);

        $porVendedor = collect($dados['por_vendedor'])->keyBy('user_id');
        $this->assertSame(2, $porVendedor[$ana->id]['leads_ativos']);
        $this->assertSame(3, $porVendedor[$ana->id]['leads_criados']);
        $this->assertSame(1, $porVendedor[$ana->id]['ganhos']);
        $this->assertEquals(5000.0, $porVendedor[$ana->id]['valor_ganho']);
        $this->assertEquals(500.0, $porVendedor[$ana->id]['valor_ponderado']);
        $this->assertEquals(100.0, $porVendedor[$ana->id]['taxa_ganho']);
        $this->assertSame(1, $porVendedor[$bia->id]['perdidos']);
        $this->assertEquals(0.0, $porVendedor[$bia->id]['taxa_ganho']);
        $this->assertSame(0, $porVendedor[$gestor->id]['leads_criados']);

        $this->assertCount(12, $dados['ganhos_por_mes']);
        $this->assertSame(1, end($dados['ganhos_por_mes'])['total']);
        $this->assertEquals(5000.0, end($dados['ganhos_por_mes'])['valor']);
    }

    public function test_pagina_e_permissao(): void
    {
        ['gestor' => $gestor, 'ana' => $ana] = $this->cenario();

        $this->actingAs($gestor)->get('/relatorios?periodo=7d')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Relatorios/Index')
            ->where('periodo.periodo', '7d')
            ->has('relatorio.por_vendedor', 3)
            ->has('agentes', 3)
            ->has('funis', 1));

        $this->actingAs($ana)->get('/relatorios')->assertForbidden();
        $this->actingAs($ana)->getJson('/api/relatorios/visao-geral')->assertForbidden();
    }

    public function test_detalhe_de_perdas_paginado_com_filtros(): void
    {
        ['gestor' => $gestor, 'bia' => $bia, 'ana' => $ana] = $this->cenario();

        $dados = $this->actingAs($gestor)->getJson('/api/relatorios/perdas/detalhe?periodo=30d')->assertOk()->json();
        $this->assertSame(1, $dados['meta']['total']);
        $linha = $dados['data'][0];
        $this->assertSame('lead', $linha['tipo']);
        $this->assertSame($bia->name, $linha['vendedor']);
        $this->assertNotNull($linha['motivo']);
        $this->assertNotNull($linha['nome']);

        $this->assertSame(0, $this->actingAs($gestor)->getJson("/api/relatorios/perdas/detalhe?periodo=30d&user_id={$ana->id}")->json('meta.total'));
        $this->assertSame(0, $this->actingAs($gestor)->getJson('/api/relatorios/perdas/detalhe?periodo=30d&tipo=projeto')->json('meta.total'));
    }
}
