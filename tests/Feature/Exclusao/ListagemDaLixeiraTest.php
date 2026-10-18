<?php

namespace Tests\Feature\Exclusao;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/** A Lixeira: leads e projetos excluídos que o agente enxerga, quem excluiu, restaurar um ou vários. */
class ListagemDaLixeiraTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_lista_leads_excluidos_com_quem_excluiu_e_respeita_a_carteira(): void
    {
        $tenant = $this->novoTenant();
        $ana = $this->agente($tenant);
        $bia = $this->agente($tenant);
        $gestor = $this->agente($tenant, 'gestor');
        $daAna = $this->lead($tenant, $ana, ['nome' => 'Lead da Ana']);
        $daBia = $this->lead($tenant, $bia, ['nome' => 'Lead da Bia']);
        $this->lead($tenant, $ana, ['nome' => 'Ativo']);

        $this->actingAs($ana)->deleteJson("/api/leads/{$daAna->id}")->assertOk();
        $this->actingAs($bia)->deleteJson("/api/leads/{$daBia->id}")->assertOk();

        $daAnaNaLista = $this->actingAs($ana)->getJson('/api/lixeira?tipo=leads')->assertOk()->json('data');
        $this->assertSame(['Lead da Ana'], array_column($daAnaNaLista, 'nome'));
        $this->assertSame($ana->name, $daAnaNaLista[0]['excluido_por']);
        $this->assertNotNull($daAnaNaLista[0]['deleted_at']);

        $doGestor = $this->actingAs($gestor)->getJson('/api/lixeira?tipo=leads&busca=bia')->assertOk()->json();
        $this->assertSame(['Lead da Bia'], array_column($doGestor['data'], 'nome'));
        $this->assertSame(1, $doGestor['meta']['total']);

        $this->actingAs($gestor)->get('/lixeira')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Lixeira/Index')
            ->has('lixeira.data', 2)
            ->where('lixeira.filtros.tipo', 'leads')
            ->has('agentes'));
    }

    public function test_projetos_na_lixeira_e_projeto_de_lead_na_lixeira(): void
    {
        $tenant = $this->novoTenant();
        $ana = $this->agente($tenant);
        $lead = $this->lead($tenant, $ana);
        $outro = $this->lead($tenant, $ana);
        $p1 = $this->projeto($lead, ['nome' => 'Proposta A']);
        $p2 = $this->projeto($outro, ['nome' => 'Proposta B']);

        $this->actingAs($ana)->deleteJson("/api/projetos/{$p1->id}")->assertOk();
        $this->actingAs($ana)->deleteJson("/api/projetos/{$p2->id}")->assertOk();
        $this->actingAs($ana)->deleteJson("/api/leads/{$outro->id}")->assertOk();

        $dados = collect($this->actingAs($ana)->getJson('/api/lixeira?tipo=projetos')->assertOk()->json('data'))->keyBy('nome');
        $this->assertFalse($dados['Proposta A']['lead_na_lixeira']);
        $this->assertTrue($dados['Proposta B']['lead_na_lixeira']);
        $this->assertSame($lead->nome, $dados['Proposta A']['lead']['nome']);

        $this->actingAs($ana)->postJson('/api/lixeira/restaurar', ['tipo' => 'projetos', 'ids' => [$p1->id, $p2->id]])
            ->assertOk()->assertJsonPath('afetados', 1)->assertJsonPath('ignorados', 1);
        $this->assertNotSoftDeleted('projetos', ['id' => $p1->id]);
        $this->assertSoftDeleted('projetos', ['id' => $p2->id]);
    }

    public function test_restaurar_leads_em_massa_so_os_meus(): void
    {
        $tenant = $this->novoTenant();
        $ana = $this->agente($tenant);
        $bia = $this->agente($tenant);
        $a = $this->lead($tenant, $ana);
        $b = $this->lead($tenant, $bia);
        $a->delete();
        $b->delete();

        $this->actingAs($ana)->postJson('/api/lixeira/restaurar', ['tipo' => 'leads', 'ids' => [$a->id, $b->id]])
            ->assertOk()->assertJsonPath('afetados', 1)->assertJsonPath('ignorados', 1);
        $this->assertNotSoftDeleted('usuarios', ['id' => $a->id]);
        $this->assertSoftDeleted('usuarios', ['id' => $b->id]);
    }

    public function test_restaurar_avisa_quando_o_estagio_foi_arquivado(): void
    {
        $tenant = $this->novoTenant();
        $ana = $this->agente($tenant);
        $funil = \App\Models\Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        \App\Models\Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 1]);
        $estagio = \App\Models\Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 2]);
        $lead = $this->lead($tenant, $ana, ['estagio_id' => $estagio->id]);
        $lead->delete();
        $estagio->delete();

        $this->actingAs($ana)->patchJson("/api/leads/{$lead->id}/restaurar")->assertOk()
            ->assertJsonPath('aviso', 'O estágio original está arquivado; mova o lead para um estágio ativo.');
    }
}
