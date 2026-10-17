<?php

namespace Tests\Feature\Reports;

use App\Http\Controllers\Userarios;
use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Statu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * As nove chaves que o Dashboard já lê saem do serviço novo com os mesmos
 * valores que Userarios::metricas() produz — a extração não mudou número.
 */
class LeadMetricsParityTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_as_nove_chaves_antigas_batem_com_a_implementacao_legada(): void
    {
        $tenant = $this->novoTenant();
        $vendedor = $this->agente($tenant);
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $aberto = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $ganho = Estagio::factory()->ganho()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $statusAberto = Statu::factory()->create(['tenant_id' => $tenant->id]);
        $statusGanho = Statu::factory()->create(['tenant_id' => $tenant->id, 'is_won' => true]);

        $a = $this->lead($tenant, $vendedor, ['estagio_id' => $aberto->id]);
        $this->lead($tenant, $vendedor, ['estagio_id' => $aberto->id]);
        $this->lead($tenant, $vendedor, ['estagio_id' => $ganho->id]);
        $this->projeto($a, ['preco' => 1000, 'status_id' => $statusAberto->id]);
        $this->projeto($a, ['preco' => 2500, 'status_id' => $statusGanho->id]);
        $this->lead($tenant, $vendedor, ['estagio_id' => $aberto->id])->delete();
        $this->lead($tenant, $this->agente($tenant), ['estagio_id' => $aberto->id]);

        $this->actingAs($vendedor);
        $legado = app(Userarios::class)->metricas(Request::create('/api/metricas'))->getData(true);
        $novo = $this->getJson('/api/metricas')->assertOk()->json();

        foreach (['leads_ativos', 'leads_arquivados', 'leads_30_dias', 'valor_projetos_abertos', 'valor_fechado_mes', 'taxa_conversao', 'total_com_projeto', 'total_leads'] as $chave) {
            $this->assertEquals($legado[$chave], $novo[$chave], "chave {$chave}");
        }
        // O novo acrescenta `cor` e `tipo` por estágio; as três chaves antigas batem.
        $soAntigas = fn (array $lista) => collect($lista)->map(fn ($e) => ['id' => $e['id'], 'descricao' => $e['descricao'], 'total' => $e['total']])->sortBy('id')->values()->all();
        $this->assertEquals($soAntigas($legado['leads_por_estagio']), $soAntigas($novo['leads_por_estagio']));
        $this->assertSame('30d', $novo['periodo']['preset']);
    }
}
