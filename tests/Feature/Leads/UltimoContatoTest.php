<?php

namespace Tests\Feature\Leads;

use App\Models\Activity;
use App\Models\Estagio;
use App\Models\Funil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * `usuarios.ultimo_contato_em` é coluna mantida por observer, não subconsulta:
 * é o que torna o filtro "parado há 14 dias" e a ordenação da grade
 * indexáveis. Contato = anotação ou movimento de estágio.
 */
class UltimoContatoTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_lead_novo_ja_tem_ultimo_contato(): void
    {
        $tenant = $this->novoTenant();
        $lead = $this->lead($tenant, $this->agente($tenant));

        $this->assertNotNull($lead->fresh()->ultimo_contato_em);
    }

    public function test_anotacao_atualiza_o_ultimo_contato_sem_gerar_evento_extra_no_log(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);

        $antes = Activity::where('lead_id', $lead->id)->count();
        $this->travelTo(now()->addHours(5));

        $this->actingAs($dono)->postJson('/api/anotacao', ['usuario_id' => $lead->id, 'descricao' => 'Ligamos e combinamos retorno.'])
            ->assertCreated();

        $this->assertTrue($lead->fresh()->ultimo_contato_em->isSameSecond(now()));
        $this->assertSame($antes + 1, Activity::where('lead_id', $lead->id)->count(), 'só o evento da anotação deve ser gravado');
    }

    public function test_mudar_de_estagio_atualiza_o_ultimo_contato(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);
        $funil = Funil::findOrFail($lead->funil_id);
        $destino = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);

        $this->travelTo(now()->addDays(3));

        $this->actingAs($dono)->patchJson("/api/usuarios/{$lead->id}/estagio", ['estagio_id' => $destino->id])->assertOk();

        $this->assertTrue($lead->fresh()->ultimo_contato_em->isSameSecond(now()));
    }
}
