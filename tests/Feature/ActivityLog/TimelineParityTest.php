<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Anotacao;
use App\Models\arquivo;
use App\Models\Projeto;
use App\Models\ProjetoAnexo;
use App\Models\ProjetoAnotacao;
use App\Models\Statu;
use App\Models\Tags;
use App\Models\Tarefa;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Porta de saída da transição: o endpoint novo precisa reproduzir o timeline()
 * antigo antes de o frontend trocar.
 *
 * A comparação é restrita ao subconjunto vivo. O timeline() antigo consulta as
 * fontes com where() simples e portanto esconde linhas soft-deleted; o log é
 * append-only e as mantém. Onde não há linha apagada os dois têm de bater
 * exatamente; onde há, a divergência é fixada por teste próprio, para ser
 * intencional e não surpresa.
 */
class TimelineParityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $staff;
    private Usuario $lead;
    private Anotacao $anotacao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
        app(CurrentTenant::class)->set($this->tenant);

        $this->lead = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->staff->id,
        ]);

        // Uma ocorrência de cada uma das sete fontes, todas vivas.
        $this->anotacao = Anotacao::create(['descricao' => 'Primeiro contato', 'usuario_id' => $this->lead->id]);
        arquivo::create(['nome' => 'rg.pdf', 'local' => 'arquivos/rg.pdf', 'usuario_id' => $this->lead->id]);

        $status = Statu::factory()->create(['tenant_id' => $this->tenant->id]);
        $projeto = Projeto::create(['nome' => 'Landing page', 'usuario_id' => $this->lead->id, 'status_id' => $status->id]);
        ProjetoAnotacao::create(['descricao' => 'Escopo fechado', 'projeto_id' => $projeto->id]);
        ProjetoAnexo::create(['nome' => 'contrato.pdf', 'local' => 'arquivos/c.pdf', 'projeto_id' => $projeto->id]);

        $this->lead->update(['tag_id' => Tags::factory()->create(['tenant_id' => $this->tenant->id])->id]);

        $tarefa = Tarefa::create([
            'usuario_id' => $this->lead->id,
            'titulo' => 'Enviar proposta',
            'data_limite' => now()->addDays(3),
        ]);
        $tarefa->update(['concluido' => true, 'concluido_em' => now()]);

        // Ambos os caminhos passam a derivar dos mesmos dados: zera o log
        // escrito pelos observers e reconstrói pelo backfill.
        DB::table('activity_log')->delete();
        $this->artisan('activities:backfill-leads')->assertSuccessful();
        app(CurrentTenant::class)->set($this->tenant);
    }

    private function tiposDoTimelineAntigo(): array
    {
        $resposta = $this->actingAs($this->staff)->getJson("/api/timeline/{$this->lead->id}");
        $resposta->assertOk();

        return collect($resposta->json())->pluck('tipo')->sort()->values()->all();
    }

    private function tiposDoEndpointNovo(): array
    {
        $resposta = $this->actingAs($this->staff)->getJson("/api/leads/{$this->lead->id}/atividades");
        $resposta->assertOk();

        return collect($resposta->json('data'))->pluck('event')->sort()->values()->all();
    }

    public function test_sem_registros_apagados_os_dois_caminhos_produzem_os_mesmos_eventos(): void
    {
        $antigo = $this->tiposDoTimelineAntigo();
        $novo = $this->tiposDoEndpointNovo();

        $this->assertNotEmpty($antigo);
        $this->assertSame($antigo, $novo);
    }

    public function test_as_nove_fontes_de_evento_estao_cobertas(): void
    {
        $this->assertSame([
            'anotacao',
            'arquivo',
            'lead_criado',
            'projeto',
            'projeto_anexo',
            'projeto_anotacao',
            'status_alterado',
            'tarefa_concluida',
            'tarefa_criada',
        ], $this->tiposDoEndpointNovo());
    }

    public function test_registro_apagado_some_do_antigo_e_vira_dois_eventos_no_novo(): void
    {
        $antesAntigo = count($this->tiposDoTimelineAntigo());
        $antesNovo = count($this->tiposDoEndpointNovo());

        $this->anotacao->delete();
        $this->artisan('activities:backfill-leads')->assertSuccessful();
        app(CurrentTenant::class)->set($this->tenant);

        // O antigo perde a anotação: a história se reescreve.
        $this->assertSame($antesAntigo - 1, count($this->tiposDoTimelineAntigo()));

        // O novo mantém a criação e acrescenta a remoção.
        $depoisNovo = $this->tiposDoEndpointNovo();
        $this->assertSame($antesNovo + 1, count($depoisNovo));
        $this->assertContains('anotacao', $depoisNovo);
        $this->assertContains('anotacao_removida', $depoisNovo);
    }

    public function test_endpoint_e_paginado_e_ordenado_do_mais_recente_para_o_mais_antigo(): void
    {
        $resposta = $this->actingAs($this->staff)->getJson("/api/leads/{$this->lead->id}/atividades");

        $resposta->assertOk()->assertJsonStructure(['data', 'current_page', 'last_page', 'total']);

        $datas = collect($resposta->json('data'))->pluck('created_at')->all();
        $ordenadas = collect($datas)->sortDesc()->values()->all();

        $this->assertSame($ordenadas, $datas);
    }

    public function test_agente_nao_acessa_atividades_de_lead_de_outro_agente(): void
    {
        $outroAgente = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($outroAgente)
            ->getJson("/api/leads/{$this->lead->id}/atividades")
            ->assertNotFound();
    }
}
