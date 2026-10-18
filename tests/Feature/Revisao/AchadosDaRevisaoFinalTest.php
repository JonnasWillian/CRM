<?php

namespace Tests\Feature\Revisao;

use App\Jobs\ImportarLeads;
use App\Models\Activity;
use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Importacao;
use App\Models\MotivoPerda;
use App\Models\Perda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/** Os achados Critical/Important da revisão final, um teste por achado. */
class AchadosDaRevisaoFinalTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    /** #1 Estágio padrão da importação precisa ser do funil escolhido. */
    public function test_importacao_recusa_estagio_de_outro_funil(): void
    {
        Storage::fake('local');
        Queue::fake();
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $vendas = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $parcerias = Funil::factory()->create(['tenant_id' => $tenant->id]);
        $deVendas = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $vendas->id]);

        $id = $this->actingAs($gestor)->postJson('/api/importacoes', ['arquivo' => UploadedFile::fake()->createWithContent('a.csv', "nome;email\nAna Souza;a@ex.test\n")])->json('data.id');
        $this->actingAs($gestor)->postJson("/api/importacoes/{$id}/iniciar", [
            'mapeamento' => ['nome' => 0, 'email' => 1],
            'opcoes' => ['duplicados' => 'ignorar', 'funil_id' => $parcerias->id, 'estagio_id' => $deVendas->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('opcoes.estagio_id');
    }

    /** #2 Quem fez importação consegue excluir a conta (a importação fica sem autor). */
    public function test_excluir_conta_de_quem_importou(): void
    {
        $tenant = $this->novoTenant();
        $this->agente($tenant, 'admin');
        $gestor = $this->agente($tenant, 'gestor');
        $imp = new Importacao(['user_id' => $gestor->id, 'arquivo' => '', 'nome_original' => 'a.csv']);
        $imp->tenant_id = $tenant->id;
        $imp->save();

        $this->actingAs($gestor)->delete('/profile', ['password' => 'password'])->assertRedirect('/');
        $this->assertNull($imp->fresh()->user_id);
    }

    /** #3 Job que morre (timeout, worker) marca a importação como falha. */
    public function test_importacao_que_falha_no_worker_nao_fica_processando(): void
    {
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $imp = new Importacao(['user_id' => $gestor->id, 'arquivo' => 'x.csv', 'nome_original' => 'a.csv', 'status' => Importacao::PROCESSANDO]);
        $imp->tenant_id = $tenant->id;
        $imp->save();

        (new ImportarLeads($imp->id))->failed(new \RuntimeException('timeout'));

        $this->assertSame(Importacao::FALHOU, $imp->fresh()->status);
        $this->assertNotNull($imp->fresh()->mensagem_falha);
    }

    /** #5 "Sem contato há +14 dias" é só de leads abertos, na grade como no tile. */
    public function test_estagnados_so_considera_leads_abertos(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $aberto = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $ganho = Estagio::factory()->ganho()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $parado = $this->lead($tenant, $dono, ['estagio_id' => $aberto->id]);
        $fechado = $this->lead($tenant, $dono, ['estagio_id' => $ganho->id]);
        foreach ([$parado, $fechado] as $l) {
            $l->forceFill(['ultimo_contato_em' => now()->subDays(30)])->saveQuietly();
        }

        $ids = array_column($this->actingAs($dono)->getJson('/api/leads?estagnados=1')->json('data'), 'id');
        $this->assertSame([$parado->id], $ids);
        $this->actingAs($dono)->getJson('/api/metricas')->assertJsonPath('estado.sem_contato_14d', 1);
    }

    /** #7 Exportar deixa rastro: quem, quando, quantos. */
    public function test_exportacao_registra_atividade(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $this->lead($tenant, $dono);
        $this->lead($tenant, $dono);

        $this->actingAs($dono)->get('/api/leads/exportar')->assertOk()->streamedContent();

        $evento = Activity::where('event', 'leads_exportados')->first();
        $this->assertNotNull($evento);
        $this->assertSame($dono->id, (int) $evento->causer_id);
        $this->assertSame(2, $evento->properties['linhas']);
    }

    /** #8 Perder um lead sem projetos registra o valor estimado como valor perdido. */
    public function test_valor_perdido_usa_o_valor_estimado(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $aberto = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $perdido = Estagio::factory()->perdido()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $motivo = MotivoPerda::factory()->create(['tenant_id' => $tenant->id]);
        $lead = $this->lead($tenant, $dono, ['estagio_id' => $aberto->id, 'valor_estimado' => 50000]);

        $this->actingAs($dono)->patchJson("/api/leads/{$lead->id}/estagio", ['estagio_id' => $perdido->id, 'perda' => ['motivo_perda_id' => $motivo->id]])->assertOk();

        $this->assertEquals(50000, Perda::first()->valor);
    }

    /** #4 Recusa de regra (papel do último admin, convite usado) vem como `message` sem `errors`, para o interceptor mostrar. */
    public function test_recusas_de_regra_chegam_como_mensagem(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $outro = $this->agente($tenant, 'admin');
        $outro->forceFill(['ativo' => false])->save();

        $this->actingAs($admin)->patchJson("/api/equipe/{$admin->id}/papel", ['papel' => 'vendedor'])
            ->assertUnprocessable()->assertJsonMissingPath('errors')->assertJsonPath('message', 'Você não pode mudar o seu próprio papel.');

        $id = $this->actingAs($admin)->postJson('/api/equipe/convites', ['email' => 'z@ex.test', 'papel' => 'vendedor'])->json('data.id');
        $this->actingAs($admin)->deleteJson("/api/equipe/convites/{$id}")->assertOk();
        $this->actingAs($admin)->postJson("/api/equipe/convites/{$id}/reenviar")
            ->assertUnprocessable()->assertJsonMissingPath('errors')->assertJsonStructure(['message']);
    }

    /** #6 Transferência em massa invalida o cache de métricas uma vez, não uma por lead. */
    public function test_transferencia_em_massa_invalida_o_cache_uma_vez(): void
    {
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $ana = $this->agente($tenant);
        $bia = $this->agente($tenant);
        for ($i = 0; $i < 15; $i++) {
            $this->lead($tenant, $ana);
        }
        $antes = \App\Support\Reports\MetricasCache::versao($tenant->id);

        $this->actingAs($gestor)->postJson('/api/leads/transferir', ['todos' => true, 'filtros' => ['user_id' => $ana->id], 'user_id' => $bia->id])
            ->assertOk()->assertJsonPath('afetados', 15);

        $this->assertSame($antes + 1, \App\Support\Reports\MetricasCache::versao($tenant->id));
    }
}
