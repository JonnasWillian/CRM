<?php

namespace Tests\Feature\Funis;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class PreferenciaDoKanbanTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;
    private User $agente;
    private Estagio $estagio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->novoTenant();
        $this->agente = $this->agente($this->tenant);
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
        $this->estagio = Estagio::factory()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);
    }

    private function salvar(mixed $valor)
    {
        return $this->actingAs($this->agente)->patchJson('/api/kanban/settings', ['default_estagio_id' => $valor]);
    }

    public function test_estagio_do_tenant_e_salvo(): void
    {
        $this->salvar($this->estagio->id)->assertOk();
        $this->assertSame($this->estagio->id, $this->agente->fresh()->kanban_default_estagio_id);
    }

    public function test_null_limpa_a_preferencia(): void
    {
        $this->salvar($this->estagio->id)->assertOk();
        $this->salvar(null)->assertOk();
        $this->assertNull($this->agente->fresh()->kanban_default_estagio_id);
    }

    public function test_estagio_de_outro_tenant_e_recusado(): void
    {
        $outro = $this->novoTenant();
        $funilAlheio = Funil::factory()->padrao()->create(['tenant_id' => $outro->id]);
        $alheio = Estagio::factory()->create(['tenant_id' => $outro->id, 'funil_id' => $funilAlheio->id]);
        $this->ativar($this->tenant);

        $this->salvar($alheio->id)->assertUnprocessable();
        $this->assertNull($this->agente->fresh()->kanban_default_estagio_id);
    }

    public function test_inexistente_arquivado_e_array_sao_recusados_com_422(): void
    {
        $this->salvar(999999)->assertUnprocessable();
        $this->salvar(['1'])->assertUnprocessable();

        $this->estagio->delete();
        $this->salvar($this->estagio->id)->assertUnprocessable();
    }
}
