<?php

namespace Tests\Feature\Legado;

use App\Models\Estagio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Fase 5: tabelas com nomes do domínio e o histórico de estágio só no
 * activity log. A normalização é testada recriando a tabela antiga (pelo
 * down() da migração que a remove), porque o RefreshDatabase já roda tudo.
 */
class MigracoesDeLegadoTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private function migracao(string $sufixo): object
    {
        return require collect(glob(database_path("migrations/*_{$sufixo}.php")))->first();
    }

    public function test_tabelas_renomeadas_e_historico_antigo_removido(): void
    {
        foreach (['anotacoes', 'projeto_anotacoes', 'projeto_anexos'] as $nova) {
            $this->assertTrue(Schema::hasTable($nova), "falta {$nova}");
        }
        foreach (['anotacaos', 'projetoAnotacaos', 'projetoAnexos', 'estagio_historicos'] as $velha) {
            $this->assertFalse(Schema::hasTable($velha), "{$velha} ainda existe");
        }
    }

    public function test_eventos_de_estagio_viram_eventos_do_lead_e_historico_sem_evento_e_criado(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);
        $a = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $lead->funil_id, 'descricao' => 'Antes']);
        $b = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $lead->funil_id, 'descricao' => 'Depois']);

        $this->migracao('drop_estagio_historicos')->down();
        $agora = now()->subDay()->startOfSecond();
        $comEvento = DB::table('estagio_historicos')->insertGetId([
            'tenant_id' => $tenant->id, 'usuario_id' => $lead->id, 'estagio_anterior_id' => $a->id, 'estagio_novo_id' => $b->id,
            'funil_anterior_id' => $lead->funil_id, 'funil_novo_id' => $lead->funil_id, 'created_at' => $agora,
        ]);
        $semEvento = DB::table('estagio_historicos')->insertGetId([
            'tenant_id' => $tenant->id, 'usuario_id' => $lead->id, 'estagio_anterior_id' => $b->id, 'estagio_novo_id' => $a->id,
            'funil_anterior_id' => $lead->funil_id, 'funil_novo_id' => $lead->funil_id, 'created_at' => $agora->copy()->addHour(),
        ]);
        DB::table('activity_log')->insert([
            'log_name' => 'default', 'description' => 'Estágio do lead alterado', 'subject_type' => 'estagio_historico', 'subject_id' => $comEvento,
            'event' => 'status_alterado', 'properties' => '[]', 'tenant_id' => $tenant->id, 'lead_id' => $lead->id, 'created_at' => $agora, 'updated_at' => $agora,
        ]);

        $this->migracao('normalizar_eventos_de_estagio_no_activity_log')->up();

        $this->assertSame(0, DB::table('activity_log')->where('subject_type', 'estagio_historico')->count());
        $eventos = DB::table('activity_log')->where('lead_id', $lead->id)->where('event', 'status_alterado')->orderBy('created_at')->get();
        $this->assertCount(2, $eventos);
        foreach ($eventos as $e) {
            $this->assertSame('lead', $e->subject_type);
            $this->assertSame($lead->id, (int) $e->subject_id);
        }
        $this->assertSame('Antes', json_decode($eventos[0]->properties, true)['estagio_anterior']);
        $this->assertSame('Depois', json_decode($eventos[0]->properties, true)['estagio_novo']);
        $this->assertSame('Depois', json_decode($eventos[1]->properties, true)['estagio_anterior']);

        // Rodar de novo não duplica.
        $this->migracao('normalizar_eventos_de_estagio_no_activity_log')->up();
        $this->assertSame(2, DB::table('activity_log')->where('lead_id', $lead->id)->where('event', 'status_alterado')->count());

        $this->migracao('drop_estagio_historicos')->up();

        $atividades = $this->actingAs($dono)->getJson("/api/leads/{$lead->id}/atividades")->json('data');
        $this->assertContains('Depois', array_column($atividades, 'estagio_novo'));
    }
}
