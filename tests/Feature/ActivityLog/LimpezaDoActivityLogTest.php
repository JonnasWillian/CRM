<?php

namespace Tests\Feature\ActivityLog;

use App\Support\ActivityLog\LeadActivity;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * `activity:limpar` apaga só o que passou da retenção, em todos os tenants,
 * sem precisar de tenant ativo — o `activitylog:clean` do pacote lança pelo
 * TenantScope fail-closed e por isso nunca pôde ser agendado.
 */
class LimpezaDoActivityLogTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    public function test_apaga_registros_antigos_de_todos_os_tenants_e_preserva_os_recentes(): void
    {
        config(['activitylog.delete_records_older_than_days' => 365]);

        [$velhaA, $novaA] = $this->atividadesDeUmTenant();
        [$velhaB, $novaB] = $this->atividadesDeUmTenant();

        app(CurrentTenant::class)->clear();

        $this->artisan('activity:limpar')->assertExitCode(0);

        $this->assertDatabaseMissing('activity_log', ['id' => $velhaA]);
        $this->assertDatabaseMissing('activity_log', ['id' => $velhaB]);
        $this->assertDatabaseHas('activity_log', ['id' => $novaA]);
        $this->assertDatabaseHas('activity_log', ['id' => $novaB]);
    }

    public function test_esta_agendado_diariamente_e_o_inspire_nao_esta_mais(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('activity:limpar')
            ->doesntExpectOutputToContain('inspire');
    }

    /** @return array{int,int} ids da atividade velha e da nova */
    private function atividadesDeUmTenant(): array
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);

        $velha = LeadActivity::registrar($lead, $lead->id, 'teste', 'velha');
        DB::table('activity_log')->where('id', $velha->id)->update(['created_at' => now()->subDays(400)]);
        $nova = LeadActivity::registrar($lead, $lead->id, 'teste', 'nova');

        return [$velha->id, $nova->id];
    }
}
