<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Activity;
use App\Models\Estagio;
use App\Models\Funil;
use App\Models\MotivoPerda;
use App\Models\Perda;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * As colunas polimórficas (activity_log.subject_type, perdas.perdivel_type)
 * guardam aliases ('lead', 'projeto'…), não o nome da classe. É o que deixa
 * renomear classes (arquivo → Arquivo, StatusProjeto → StatusProjeto) sem migrar
 * dados de log.
 */
class MorphMapTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_eventos_e_perdas_novos_gravam_o_alias_e_resolvem_o_model(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);
        $funil = Funil::findOrFail($lead->funil_id);
        $perdido = Estagio::factory()->perdido()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $motivo = MotivoPerda::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($dono)->patchJson("/api/leads/{$lead->id}/estagio", [
            'estagio_id' => $perdido->id, 'perda' => ['motivo_perda_id' => $motivo->id],
        ])->assertOk();

        $this->assertSame('lead', DB::table('activity_log')->where('lead_id', $lead->id)->value('subject_type'));
        $this->assertSame('user', DB::table('activity_log')->where('lead_id', $lead->id)->whereNotNull('causer_type')->value('causer_type'));
        $this->assertSame('lead', DB::table('perdas')->value('perdivel_type'));

        $this->assertInstanceOf(Usuario::class, Activity::where('lead_id', $lead->id)->first()->subject);
        $this->assertInstanceOf(Usuario::class, Perda::first()->perdivel);
        $this->assertSame(1, Perda::deLeads()->count());
        $this->assertSame(0, Perda::deProjetos()->count());
    }

    public function test_a_migration_reescreve_linhas_antigas_com_nome_de_classe(): void
    {
        $tenant = $this->novoTenant();
        $lead = $this->lead($tenant, $this->agente($tenant));

        DB::table('activity_log')->insert([
            'log_name' => 'default', 'description' => 'legado', 'event' => 'teste',
            'subject_type' => 'App\\Models\\Usuario', 'subject_id' => $lead->id,
            'causer_type' => 'App\\Models\\User', 'causer_id' => $lead->user_id,
            'tenant_id' => $tenant->id, 'lead_id' => $lead->id, 'properties' => '[]',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('perdas')->insert([
            'tenant_id' => $tenant->id, 'perdivel_type' => 'App\\Models\\Projeto', 'perdivel_id' => 1,
            'motivo_perda_id' => MotivoPerda::factory()->create(['tenant_id' => $tenant->id])->id,
            'valor' => null, 'user_id' => $lead->user_id, 'created_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_17_100007_morph_map_no_activity_log_e_perdas.php');
        $migration->up();

        $this->assertSame('lead', DB::table('activity_log')->where('description', 'legado')->value('subject_type'));
        $this->assertSame('user', DB::table('activity_log')->where('description', 'legado')->value('causer_type'));
        $this->assertSame('projeto', DB::table('perdas')->value('perdivel_type'));
    }
}
