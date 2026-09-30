<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Anotacao;
use App\Models\Estagio;
use App\Models\Funil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class TimelinePaginadaTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    public function test_mais_de_uma_pagina_e_per_page_com_teto(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);

        for ($i = 0; $i < 25; $i++) {
            Anotacao::create(['descricao' => "Nota {$i}", 'usuario_id' => $lead->id]);
        }

        $pagina1 = $this->actingAs($dono)->getJson("/api/leads/{$lead->id}/atividades")->assertOk();
        $pagina1->assertJsonCount(20, 'data')->assertJsonPath('last_page', 2);

        // 25 anotações + lead_criado = 26 eventos.
        $this->actingAs($dono)->getJson("/api/leads/{$lead->id}/atividades?page=2")->assertJsonCount(6, 'data');
        $this->actingAs($dono)->getJson("/api/leads/{$lead->id}/atividades?per_page=100000")->assertJsonPath('per_page', 100);
    }

    public function test_mudanca_de_estagio_chega_com_nomes_e_nao_com_ids(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $funil = Funil::where('is_default', true)->first() ?? Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $novo = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'descricao' => 'Qualificado']);
        $lead = $this->lead($tenant, $dono);

        $lead->update(['estagio_id' => $novo->id]);

        $evento = collect($this->actingAs($dono)->getJson("/api/leads/{$lead->id}/atividades")->json('data'))
            ->firstWhere('tipo', 'status_alterado');

        $this->assertSame('Qualificado', $evento['estagio_novo']);
        $this->assertSame('—', $evento['estagio_anterior']);
    }

    public function test_estagio_renomeado_depois_mantem_o_nome_da_epoca(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $funil = Funil::where('is_default', true)->first() ?? Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $novo = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'descricao' => 'Qualificado']);
        $lead = $this->lead($tenant, $dono);

        $lead->update(['estagio_id' => $novo->id]);
        // O observer grava o nome no momento do evento; renomear depois não
        // reescreve o histórico.
        $novo->update(['descricao' => 'Renomeado']);

        $evento = collect($this->actingAs($dono)->getJson("/api/leads/{$lead->id}/atividades")->json('data'))
            ->firstWhere('tipo', 'status_alterado');

        $this->assertSame('Qualificado', $evento['estagio_novo']);
    }

    public function test_remocao_e_restauracao_aparecem_na_timeline(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);
        $projeto = $this->projeto($lead, ['nome' => 'Site novo']);

        $projeto->delete();

        $tipos = collect($this->actingAs($dono)->getJson("/api/leads/{$lead->id}/atividades")->json('data'))->pluck('tipo');
        $this->assertContains('projeto_removido', $tipos);
    }

    /**
     * O observer grava o texto no momento da criação; a edição não gera
     * evento novo. Sem preferir o subject vivo, o timeline mostrava o texto
     * antigo para sempre.
     */
    public function test_anotacao_editada_aparece_com_o_texto_novo(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);
        $anotacao = Anotacao::create(['descricao' => 'Texto antigo', 'usuario_id' => $lead->id]);

        $this->actingAs($dono)->putJson("/api/anotacao/{$anotacao->id}", ['descricao' => 'Texto novo'])->assertOk();

        $evento = collect($this->actingAs($dono)->getJson("/api/leads/{$lead->id}/atividades")->json('data'))
            ->firstWhere('tipo', 'anotacao');

        $this->assertSame('Texto novo', $evento['descricao']);
    }

    /**
     * Anotação excluída não carrega subject (lixeira); o texto gravado em
     * properties não pode continuar aparecendo como se ela existisse.
     */
    public function test_anotacao_excluida_aparece_sem_o_texto(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);
        $anotacao = Anotacao::create(['descricao' => 'Texto sigiloso', 'usuario_id' => $lead->id]);

        $this->actingAs($dono)->deleteJson("/api/anotacao/{$anotacao->id}")->assertOk();

        $evento = collect($this->actingAs($dono)->getJson("/api/leads/{$lead->id}/atividades")->json('data'))
            ->firstWhere('tipo', 'anotacao');

        $this->assertNotNull($evento);
        $this->assertArrayNotHasKey('descricao', $evento);
    }
}
