<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Activity;
use App\Models\Anotacao;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * O backfill gera os eventos retroativos das linhas que existiam antes dos
 * observers. Precisa ser seguro de repetir — um comando de migração de dados
 * que não pode ser rodado duas vezes é um comando que ninguém roda com
 * confiança.
 */
class BackfillIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    private function leadComHistoricoPreExistente(Tenant $tenant): Usuario
    {
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        app(CurrentTenant::class)->set($tenant);

        $lead = Usuario::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
        ]);

        $anotacao = Anotacao::create([
            'descricao' => 'Anotação anterior ao activity log',
            'usuario_id' => $lead->id,
        ]);
        $anotacaoApagada = Anotacao::create([
            'descricao' => 'Anotação que foi apagada',
            'usuario_id' => $lead->id,
        ]);
        $anotacaoApagada->delete();

        // Simula o estado pré-observers: as linhas existem, o log não.
        DB::table('activity_log')->delete();

        return $lead;
    }

    public function test_rodar_duas_vezes_nao_duplica(): void
    {
        $tenant = Tenant::factory()->create();
        $this->leadComHistoricoPreExistente($tenant);

        $this->artisan('activities:backfill-leads')->assertSuccessful();

        app(CurrentTenant::class)->set($tenant);
        $depoisDaPrimeira = Activity::count();
        $this->assertGreaterThan(0, $depoisDaPrimeira, 'a primeira execucao precisa gerar eventos');

        $this->artisan('activities:backfill-leads')->assertSuccessful();

        app(CurrentTenant::class)->set($tenant);
        $this->assertSame(
            $depoisDaPrimeira,
            Activity::count(),
            'a segunda execucao nao pode gerar nenhum evento novo'
        );
    }

    public function test_linha_apagada_gera_criacao_e_remocao(): void
    {
        $tenant = Tenant::factory()->create();
        $this->leadComHistoricoPreExistente($tenant);

        $this->artisan('activities:backfill-leads')->assertSuccessful();

        app(CurrentTenant::class)->set($tenant);

        $this->assertCount(2, Activity::where('event', 'anotacao')->get());
        $this->assertCount(1, Activity::where('event', 'anotacao_removida')->get());
    }

    public function test_evento_retroage_a_data_de_origem(): void
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        app(CurrentTenant::class)->set($tenant);

        $lead = Usuario::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $staff->id]);
        $anotacao = Anotacao::create(['descricao' => 'Antiga', 'usuario_id' => $lead->id]);

        $dataAntiga = now()->subMonths(6)->startOfSecond();
        DB::table('anotacaos')->where('id', $anotacao->id)->update(['created_at' => $dataAntiga]);
        DB::table('activity_log')->delete();

        $this->artisan('activities:backfill-leads')->assertSuccessful();

        app(CurrentTenant::class)->set($tenant);
        $atividade = Activity::where('event', 'anotacao')->first();

        $this->assertNotNull($atividade);
        $this->assertSame(
            $dataAntiga->toDateTimeString(),
            $atividade->created_at->toDateTimeString(),
            'sem retroagir created_at a ordenacao da timeline morre'
        );
    }

    public function test_backfill_nao_mistura_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $leadA = $this->leadComHistoricoPreExistente($tenantA);
        $leadB = $this->leadComHistoricoPreExistente($tenantB);

        $this->artisan('activities:backfill-leads')->assertSuccessful();

        app(CurrentTenant::class)->set($tenantA);
        $this->assertSame(
            Activity::count(),
            Activity::where('lead_id', $leadA->id)->count(),
            'tenant A nao pode enxergar nem possuir eventos do tenant B'
        );
    }
}
