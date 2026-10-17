<?php

namespace Tests\Feature\Reports;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Reports\LeadMetricsService;
use App\Services\Reports\Periodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class LeadMetricsCacheTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private function resumo(Tenant $tenant, ?User $agente, ?Periodo $periodo = null): array
    {
        return app(LeadMetricsService::class)->resumo($tenant, $agente, $periodo ?? Periodo::padrao());
    }

    public function test_segunda_chamada_vem_do_cache_sem_consultar_o_banco(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $this->lead($tenant, $dono);

        $this->resumo($tenant, $dono);

        DB::enableQueryLog();
        $this->resumo($tenant, $dono);
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_cache_nao_vaza_entre_tenants_agentes_e_periodos(): void
    {
        $a = $this->novoTenant();
        $donoA = $this->agente($a);
        $this->lead($a, $donoA);
        $this->lead($a, $donoA);
        $colegaA = $this->agente($a);
        $this->lead($a, $colegaA);
        $this->ativar($a);

        $this->assertSame(2, $this->resumo($a, $donoA)['total_leads']);
        $this->assertSame(3, $this->resumo($a, null)['total_leads'], 'agente nulo = tenant inteiro, não o cache do vendedor');

        // Um lead de 10 dias atrás: dentro de 30d, fora de 7d. Se o período não
        // entrasse na chave, a segunda chamada devolveria o número da primeira.
        $this->travel(-10)->days();
        $this->lead($a, $donoA);
        $this->travelBack();
        $this->ativar($a);
        $this->assertSame(3, $this->resumo($a, $donoA, Periodo::preset('30d'))['leads_30_dias']);
        $this->assertSame(2, $this->resumo($a, $donoA, Periodo::preset('7d'))['leads_30_dias']);

        $b = $this->novoTenant();
        $donoB = $this->agente($b);
        $this->lead($b, $donoB);
        $this->assertSame(1, $this->resumo($b, $donoB)['total_leads']);
    }

    public function test_guarda_de_tenant_lanca_quando_o_tenant_ativo_e_outro(): void
    {
        $a = $this->novoTenant();
        $b = $this->novoTenant();
        $this->ativar($b);

        $this->expectException(\RuntimeException::class);
        $this->resumo($a, null);
    }

    public function test_cadastrar_lead_invalida_o_cache(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $this->lead($tenant, $dono);

        $this->assertSame(1, $this->resumo($tenant, $dono)['total_leads']);
        $this->lead($tenant, $dono);
        $this->assertSame(2, $this->resumo($tenant, $dono)['total_leads']);
    }
}
