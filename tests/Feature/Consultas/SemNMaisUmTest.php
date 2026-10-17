<?php

namespace Tests\Feature\Consultas;

use App\Models\Funil;
use App\Models\MotivoPerda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Listar N funis ou N motivos custa o mesmo número de consultas que listar 2.
 * As contagens (`total_leads`, `total_perdas`) saem numa consulta só, não
 * numa por linha.
 */
class SemNMaisUmTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    public function test_listar_funis_nao_cresce_com_o_numero_de_funis(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');

        Funil::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        $comDois = $this->consultasDe($admin, '/api/funis');

        Funil::factory()->count(6)->create(['tenant_id' => $tenant->id]);
        $comOito = $this->consultasDe($admin, '/api/funis');

        $this->assertSame($comDois, $comOito, "2 funis: {$comDois} consultas; 8 funis: {$comOito}");
    }

    public function test_listar_motivos_nao_cresce_com_o_numero_de_motivos(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');

        MotivoPerda::factory()->count(2)->create(['tenant_id' => $tenant->id]);
        $comDois = $this->consultasDe($admin, '/api/motivos-perda?incluir_arquivados=1');

        MotivoPerda::factory()->count(6)->create(['tenant_id' => $tenant->id]);
        $comOito = $this->consultasDe($admin, '/api/motivos-perda?incluir_arquivados=1');

        $this->assertSame($comDois, $comOito, "2 motivos: {$comDois} consultas; 8 motivos: {$comOito}");
    }

    private function consultasDe(User $agente, string $url): int
    {
        // Aquecimento: a primeira chamada paga o cache de permissões do spatie,
        // que não é o que se mede aqui.
        $this->actingAs($agente)->getJson($url)->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($agente)->getJson($url)->assertOk();

        $total = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $total;
    }
}
