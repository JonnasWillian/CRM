<?php

namespace Tests\Feature\DataIntegrity;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Um estágio arquivado (soft delete) não pode levar embora os leads que ainda
 * estão nele.
 *
 * O Kanban distribui os leads pelas colunas comparando `lead.estagio_id` com o
 * `id` de cada estágio retornado. Se a coluna some da payload enquanto o lead
 * continua apontando para ela, o lead não renderiza em coluna nenhuma — some
 * do quadro em silêncio, ainda que o contador do topo continue o somando.
 *
 * Por isso a coluna arquivada continua vindo *enquanto tiver leads*, marcada
 * com `arquivada`, para que a UI a mostre como somente-saída. Esvaziada, ela
 * desaparece sozinha.
 *
 * Com funis múltiplos, o quadro passou a ser sempre o de UM funil, e o lead
 * precisa pertencer a ele para aparecer — daí o funil explícito no cenário.
 */
class KanbanArchivedEstagioTest extends TestCase
{
    use RefreshDatabase;

    private function cenario(): array
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        app(CurrentTenant::class)->set($tenant);

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);

        return [$tenant, $staff, $funil];
    }

    public function test_coluna_arquivada_continua_visivel_enquanto_tiver_leads(): void
    {
        [$tenant, $staff, $funil] = $this->cenario();

        $estagio = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $lead = Usuario::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
            'funil_id' => $funil->id,
            'estagio_id' => $estagio->id,
        ]);

        $estagio->delete();

        $dados = $this->actingAs($staff)->postJson('/api/kanban')->assertOk()->json();

        $coluna = collect($dados['estagios'])->firstWhere('id', $estagio->id);

        $this->assertNotNull($coluna, 'a coluna arquivada sumiu e levou o lead junto');
        $this->assertTrue($coluna['arquivada']);
        $this->assertContains($lead->id, array_column($dados['leads'], 'id'));
    }

    public function test_coluna_arquivada_sem_leads_nao_aparece(): void
    {
        [$tenant, $staff, $funil] = $this->cenario();

        $estagio = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $estagio->delete();

        $dados = $this->actingAs($staff)->postJson('/api/kanban')->assertOk()->json();

        $this->assertNotContains($estagio->id, array_column($dados['estagios'], 'id'));
    }

    public function test_coluna_ativa_vem_sem_a_marca_de_arquivada(): void
    {
        [$tenant, $staff, $funil] = $this->cenario();

        $estagio = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);

        $dados = $this->actingAs($staff)->postJson('/api/kanban')->assertOk()->json();

        $coluna = collect($dados['estagios'])->firstWhere('id', $estagio->id);

        $this->assertNotNull($coluna);
        $this->assertFalse($coluna['arquivada']);
    }

    /**
     * O quadro é de um funil só. Um estágio de outro funil do MESMO tenant não
     * pode aparecer como coluna — o TenantScope não pegaria isso, já que os
     * dois funis são da mesma empresa.
     */
    public function test_quadro_nao_mistura_estagios_de_outro_funil(): void
    {
        [$tenant, $staff, $funil] = $this->cenario();

        $daqui = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);

        $outroFunil = Funil::factory()->create(['tenant_id' => $tenant->id]);
        $dela = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $outroFunil->id]);

        $dados = $this->actingAs($staff)->postJson('/api/kanban', ['funil_id' => $funil->id])->assertOk()->json();

        $ids = array_column($dados['estagios'], 'id');
        $this->assertContains($daqui->id, $ids);
        $this->assertNotContains($dela->id, $ids);
    }
}
