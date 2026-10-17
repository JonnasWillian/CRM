<?php

namespace Tests\Feature\Leads;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\MotivoPerda;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Ações em massa da grade: por lista de ids (até o teto) ou por "todos os
 * resultados" do filtro atual. Só os leads que o agente enxerga entram;
 * os demais são ignorados e contados.
 */
class AcoesEmMassaTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private function cenario(): array
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $colega = $this->agente($tenant);
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $aberto = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 1]);
        $outro = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 2]);
        $perdido = Estagio::factory()->perdido()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 3]);
        $meus = collect(range(1, 3))->map(fn () => $this->lead($tenant, $dono, ['estagio_id' => $aberto->id]));
        $doColega = $this->lead($tenant, $colega, ['estagio_id' => $aberto->id]);

        return compact('tenant', 'dono', 'colega', 'funil', 'aberto', 'outro', 'perdido', 'meus', 'doColega');
    }

    public function test_mover_estagio_por_ids_ignora_o_que_nao_e_meu(): void
    {
        ['dono' => $dono, 'outro' => $outro, 'meus' => $meus, 'doColega' => $doColega, 'aberto' => $aberto] = $this->cenario();

        $this->actingAs($dono)->postJson('/api/leads/mover-estagio', [
            'ids' => [...$meus->pluck('id')->all(), $doColega->id],
            'estagio_id' => $outro->id,
        ])->assertOk()->assertJsonPath('afetados', 3)->assertJsonPath('ignorados', 1);

        $this->assertSame(3, Usuario::where('estagio_id', $outro->id)->count());
        $this->assertSame($aberto->id, $doColega->fresh()->estagio_id);
    }

    public function test_mover_para_perdido_exige_motivo_e_registra_uma_perda_por_lead(): void
    {
        ['tenant' => $tenant, 'dono' => $dono, 'perdido' => $perdido, 'meus' => $meus] = $this->cenario();

        $this->actingAs($dono)->postJson('/api/leads/mover-estagio', ['ids' => $meus->pluck('id')->all(), 'estagio_id' => $perdido->id])
            ->assertUnprocessable()->assertJsonValidationErrors('perda.motivo_perda_id');

        $motivo = MotivoPerda::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($dono)->postJson('/api/leads/mover-estagio', [
            'ids' => $meus->pluck('id')->all(), 'estagio_id' => $perdido->id, 'perda' => ['motivo_perda_id' => $motivo->id],
        ])->assertOk()->assertJsonPath('afetados', 3);

        $this->assertSame(3, \App\Models\Perda::deLeads()->count());
    }

    public function test_todos_os_resultados_usa_os_filtros_da_grade(): void
    {
        ['dono' => $dono, 'outro' => $outro, 'meus' => $meus] = $this->cenario();
        $meus[0]->forceFill(['nome' => 'Alfa Comercial'])->saveQuietly();

        $this->actingAs($dono)->postJson('/api/leads/mover-estagio', ['todos' => true, 'filtros' => ['busca' => 'alfa'], 'estagio_id' => $outro->id])
            ->assertOk()->assertJsonPath('afetados', 1);

        $this->assertSame($outro->id, $meus[0]->fresh()->estagio_id);
        $this->assertNotSame($outro->id, $meus[1]->fresh()->estagio_id);
    }

    public function test_lixeira_em_massa_e_restauracao(): void
    {
        ['dono' => $dono, 'meus' => $meus, 'doColega' => $doColega] = $this->cenario();

        $this->actingAs($dono)->postJson('/api/leads/lixeira', ['ids' => [$meus[0]->id, $meus[1]->id, $doColega->id]])
            ->assertOk()->assertJsonPath('afetados', 2)->assertJsonPath('ignorados', 1);

        $this->assertSoftDeleted('usuarios', ['id' => $meus[0]->id]);
        $this->assertNotSoftDeleted('usuarios', ['id' => $doColega->id]);

        $this->actingAs($dono)->patchJson("/api/leads/{$meus[0]->id}/restaurar")->assertOk();
        $this->assertNotSoftDeleted('usuarios', ['id' => $meus[0]->id]);
    }

    public function test_teto_de_ids_e_estagio_de_outro_funil(): void
    {
        ['tenant' => $tenant, 'dono' => $dono, 'meus' => $meus] = $this->cenario();

        $this->actingAs($dono)->postJson('/api/leads/lixeira', ['ids' => range(1, 201)])->assertUnprocessable()->assertJsonValidationErrors('ids');

        $outroFunil = Funil::factory()->create(['tenant_id' => $tenant->id]);
        $estagioAlheio = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $outroFunil->id]);
        $this->actingAs($dono)->postJson('/api/leads/mover-estagio', ['ids' => $meus->pluck('id')->all(), 'estagio_id' => $estagioAlheio->id])
            ->assertOk()->assertJsonPath('afetados', 0)->assertJsonPath('ignorados', 3);
    }
}
