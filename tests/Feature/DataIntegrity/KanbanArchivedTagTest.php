<?php

namespace Tests\Feature\DataIntegrity;

use App\Models\Tags;
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
 * O Kanban distribui os leads pelas colunas comparando `lead.tag_id` com o
 * `id` de cada tag retornada. Se a coluna some da payload enquanto o lead
 * continua apontando para ela, o lead não renderiza em coluna nenhuma — some
 * do quadro em silêncio, ainda que o contador do topo continue o somando.
 *
 * Por isso a coluna arquivada continua vindo *enquanto tiver leads*, marcada
 * com `arquivada`, para que a UI a mostre como somente-saída. Esvaziada, ela
 * desaparece sozinha.
 */
class KanbanArchivedTagTest extends TestCase
{
    use RefreshDatabase;

    private function cenario(): array
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        app(CurrentTenant::class)->set($tenant);

        return [$tenant, $staff];
    }

    public function test_coluna_arquivada_continua_visivel_enquanto_tiver_leads(): void
    {
        [$tenant, $staff] = $this->cenario();

        $tag = Tags::factory()->create(['tenant_id' => $tenant->id]);
        $lead = Usuario::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
            'tag_id' => $tag->id,
        ]);

        $tag->delete();

        $dados = $this->actingAs($staff)->postJson('/api/kanban')->assertOk()->json();

        $coluna = collect($dados['tags'])->firstWhere('id', $tag->id);

        $this->assertNotNull($coluna, 'a coluna arquivada sumiu e levou o lead junto');
        $this->assertTrue($coluna['arquivada']);
        $this->assertContains($lead->id, array_column($dados['leads'], 'id'));
    }

    public function test_coluna_arquivada_sem_leads_nao_aparece(): void
    {
        [$tenant, $staff] = $this->cenario();

        $tag = Tags::factory()->create(['tenant_id' => $tenant->id]);
        $tag->delete();

        $dados = $this->actingAs($staff)->postJson('/api/kanban')->assertOk()->json();

        $this->assertNotContains($tag->id, array_column($dados['tags'], 'id'));
    }

    public function test_coluna_ativa_vem_sem_a_marca_de_arquivada(): void
    {
        [$tenant, $staff] = $this->cenario();

        $tag = Tags::factory()->create(['tenant_id' => $tenant->id]);

        $dados = $this->actingAs($staff)->postJson('/api/kanban')->assertOk()->json();

        $coluna = collect($dados['tags'])->firstWhere('id', $tag->id);

        $this->assertNotNull($coluna);
        $this->assertFalse($coluna['arquivada']);
    }
}
