<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Anotacao;
use App\Models\Arquivo;
use App\Models\Estagio;
use App\Models\Projeto;
use App\Models\ProjetoAnexo;
use App\Models\ProjetoAnotacao;
use App\Models\StatusProjeto;
use App\Models\Tarefa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * O histórico do lead (GET api/leads/{id}/atividades) cobre as nove fontes
 * de evento e traz, para cada tipo, os campos que o TimelinePanel lê.
 * Substitui o teste de paridade com o timeline antigo, removido na Fase 5.
 */
class AtividadeResourceTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_as_nove_fontes_e_os_campos_de_cada_tipo(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);
        $origem = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $lead->funil_id, 'descricao' => 'Origem']);
        $destino = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $lead->funil_id, 'descricao' => 'Destino']);
        $lead->update(['estagio_id' => $origem->id]);

        Anotacao::create(['descricao' => 'Primeiro contato', 'usuario_id' => $lead->id]);
        Arquivo::create(['nome' => 'rg.pdf', 'local' => 'arquivos/rg.pdf', 'usuario_id' => $lead->id]);
        $projeto = Projeto::create(['nome' => 'Landing page', 'usuario_id' => $lead->id, 'status_id' => StatusProjeto::factory()->create(['tenant_id' => $tenant->id])->id]);
        ProjetoAnotacao::create(['descricao' => 'Escopo fechado', 'projeto_id' => $projeto->id]);
        ProjetoAnexo::create(['nome' => 'contrato.pdf', 'local' => 'arquivos/c.pdf', 'projeto_id' => $projeto->id]);
        $lead->update(['estagio_id' => $destino->id]);
        $tarefa = Tarefa::create(['usuario_id' => $lead->id, 'titulo' => 'Enviar proposta', 'data_limite' => now()->addDays(3)]);
        $tarefa->update(['concluido' => true, 'concluido_em' => now()]);

        $eventos = collect($this->actingAs($dono)->getJson("/api/leads/{$lead->id}/atividades?per_page=100")->assertOk()->json('data'));

        foreach (['lead_criado', 'anotacao', 'arquivo', 'projeto', 'projeto_anotacao', 'projeto_anexo', 'status_alterado', 'tarefa_criada', 'tarefa_concluida'] as $tipo) {
            $this->assertTrue($eventos->contains('tipo', $tipo), "falta o tipo {$tipo}");
        }

        $porTipo = $eventos->keyBy('tipo');
        $this->assertSame('Primeiro contato', $porTipo['anotacao']['descricao']);
        $this->assertSame('rg.pdf', $porTipo['arquivo']['nome']);
        $this->assertSame('Landing page', $porTipo['projeto_anexo']['projeto_nome']);
        $this->assertSame('Escopo fechado', $porTipo['projeto_anotacao']['descricao']);
        $this->assertSame('Enviar proposta', $porTipo['tarefa_concluida']['titulo']);
        $mudanca = $eventos->where('tipo', 'status_alterado')->firstWhere('estagio_novo', 'Destino');
        $this->assertSame('Origem', $mudanca['estagio_anterior']);
    }
}
