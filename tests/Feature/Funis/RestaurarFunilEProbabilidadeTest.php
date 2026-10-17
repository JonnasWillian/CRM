<?php

namespace Tests\Feature\Funis;

use App\Models\Estagio;
use App\Models\Funil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class RestaurarFunilEProbabilidadeTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_funil_arquivado_pode_ser_restaurado_com_seus_estagios(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);

        $id = $this->actingAs($admin)->postJson('/api/funis', ['nome' => 'Pós-venda'])->assertCreated()->json('id');
        $estagios = Estagio::where('funil_id', $id)->count();
        $this->assertGreaterThan(0, $estagios);

        $this->actingAs($admin)->deleteJson("/api/funis/{$id}")->assertOk();
        $this->assertNull(Funil::find($id));

        $this->actingAs($admin)->patchJson("/api/funis/{$id}/restaurar")->assertOk()->assertJsonPath('id', $id);
        $this->assertNotNull(Funil::find($id));
        $this->assertSame($estagios, Estagio::where('funil_id', $id)->count());
    }

    public function test_restaurar_exige_configuracoes_manage(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $vendedor = $this->agente($tenant, 'vendedor');
        Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $id = $this->actingAs($admin)->postJson('/api/funis', ['nome' => 'Pós-venda'])->json('id');
        $this->actingAs($admin)->deleteJson("/api/funis/{$id}")->assertOk();

        $this->actingAs($vendedor)->patchJson("/api/funis/{$id}/restaurar")->assertForbidden();
    }

    public function test_estagio_aceita_probabilidade_padrao_entre_0_e_100(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin)->postJson("/api/funis/{$funil->id}/estagios", [
            'descricao' => 'Proposta enviada', 'tipo' => 'aberto', 'probabilidade_padrao' => 40,
        ])->assertCreated()->assertJsonPath('probabilidade_padrao', 40);

        $this->actingAs($admin)->postJson("/api/funis/{$funil->id}/estagios", [
            'descricao' => 'Proposta enviada', 'tipo' => 'aberto', 'probabilidade_padrao' => 101,
        ])->assertStatus(422);
    }
}
