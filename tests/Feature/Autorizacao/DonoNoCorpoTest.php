<?php

namespace Tests\Feature\Autorizacao;

use App\Models\Funil;
use App\Models\Projeto;
use App\Models\StatusProjeto;
use App\Models\TarefaPadrao;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Endpoints que recebem o id do dono por um campo do CORPO.
 *
 * O route model binding só enxerga parâmetros de URL, então nestes três
 * caminhos nenhuma policy rodava sozinha: bastava enviar o id do colega no
 * corpo. É a mesma classe de IDOR que o resto do plano fecha, por outra porta.
 */
class DonoNoCorpoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $dono;

    private User $colega;

    private Usuario $lead;

    private Projeto $projeto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->dono = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->colega = User::factory()->create(['tenant_id' => $this->tenant->id]);

        app(CurrentTenant::class)->set($this->tenant);
        setPermissionsTeamId($this->tenant->id);
        $this->dono->assignRole('vendedor');
        $this->colega->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
        $this->lead = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->dono->id, 'funil_id' => $funil->id,
        ]);

        $status = StatusProjeto::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->projeto = new Projeto;
        $this->projeto->fill(['nome' => 'Proposta', 'usuario_id' => $this->lead->id, 'status_id' => $status->id]);
        $this->projeto->tenant_id = $this->tenant->id;
        $this->projeto->save();
    }

    public function test_listar_projetos_exige_posse_do_lead(): void
    {
        $this->actingAs($this->colega)
            ->getJson("/api/leads/{$this->lead->id}/projetos")
            ->assertNotFound();

        $this->actingAs($this->dono)
            ->getJson("/api/leads/{$this->lead->id}/projetos")
            ->assertOk();
    }

    /**
     * A pior das três: `usuario_id` só tinha `required` — nem existência, nem
     * tenant, nem posse.
     */
    public function test_anotar_em_lead_de_colega_e_recusado(): void
    {
        $this->actingAs($this->colega)
            ->postJson("/api/leads/{$this->lead->id}/anotacoes", ['descricao' => 'Intrusa'])
            ->assertNotFound();

        $this->assertDatabaseMissing('anotacoes', ['descricao' => 'Intrusa']);
    }

    public function test_anotar_em_projeto_de_colega_e_recusado(): void
    {
        $this->actingAs($this->colega)
            ->postJson("/api/projetos/{$this->projeto->id}/anotacoes", ['descricao' => 'Intrusa'])
            ->assertNotFound();

        $this->assertDatabaseMissing('projeto_anotacoes', ['descricao' => 'Intrusa']);
    }

    /**
     * Id inexistente e id de terceiro precisam dar a MESMA resposta, senão a
     * diferença revela quais ids existem no tenant.
     *
     * Comparar só `->status()` era exatamente o oráculo que a rodada anterior
     * fechou: os status batiam (os dois 404), mas os CORPOS podiam divergir —
     * era assim que "No query results for model [App\Models\Usuario] 999999"
     * (vazando classe e id) se distinguia de "Not Found" (da policy) mesmo
     * com o mesmo status. Comparar o corpo também é o que faz este teste
     * medir o que o nome promete.
     */
    public function test_id_inexistente_e_id_alheio_respondem_igual(): void
    {
        $alheio = $this->actingAs($this->colega)
            ->postJson("/api/leads/{$this->lead->id}/anotacoes", ['descricao' => 'A']);

        $inexistente = $this->actingAs($this->colega)
            ->postJson('/api/leads/999999/anotacoes', ['descricao' => 'A']);

        $this->assertSame(
            $alheio->status().' '.$alheio->getContent(),
            $inexistente->status().' '.$inexistente->getContent(),
            'a diferença de status ou de corpo revelaria quais ids existem',
        );
    }

    /**
     * Rodada de correção 1: mesma classe de buraco, achada porque o grep
     * original não pegava acesso via `$validated['usuario_id']`.
     */
    public function test_criar_tarefa_em_lead_de_colega_e_recusado(): void
    {
        $this->actingAs($this->colega)
            ->postJson("/api/leads/{$this->lead->id}/tarefas", [
                'titulo' => 'Intrusa',
                'data_limite' => now()->addDay()->toDateString(),
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('tarefas', ['titulo' => 'Intrusa']);
    }

    public function test_aplicar_modelo_de_tarefa_em_lead_de_colega_e_recusado(): void
    {
        $padrao = new TarefaPadrao;
        $padrao->fill(['user_id' => $this->colega->id, 'titulo' => 'Follow-up', 'prazo_dias' => 3]);
        $padrao->tenant_id = $this->tenant->id;
        $padrao->save();

        $this->actingAs($this->colega)
            ->postJson("/api/leads/{$this->lead->id}/tarefas/aplicar-modelos", [
                'padroes' => [$padrao->id],
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('tarefas', ['titulo' => 'Follow-up']);
    }

    public function test_dono_anota_no_proprio_lead(): void
    {
        $this->actingAs($this->dono)
            ->postJson("/api/leads/{$this->lead->id}/anotacoes", ['descricao' => 'Legitima'])
            ->assertCreated();
    }

    public function test_dono_anota_no_proprio_projeto(): void
    {
        $this->actingAs($this->dono)
            ->postJson("/api/projetos/{$this->projeto->id}/anotacoes", ['descricao' => 'Legitima'])
            ->assertCreated();
    }

    /**
     * Rodada de correção 2: a oitava porta, e a pior — `usuario_id` só tinha
     * `required` na criação de projeto. Nem existência, nem tenant, nem posse.
     */
    public function test_criar_projeto_em_lead_de_colega_e_recusado(): void
    {
        $status = StatusProjeto::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->colega)
            ->postJson("/api/leads/{$this->lead->id}/projetos", [
                'nome' => 'Projeto intruso',
                'status_id' => $status->id,
            ])
            ->assertNotFound(); // lead alheio: a mesma resposta de id inexistente

        $this->assertDatabaseMissing('projetos', ['nome' => 'Projeto intruso']);
    }

    /** O dono vem da ROTA; um `usuario_id` no corpo (até em array) é ignorado. */
    public function test_id_no_corpo_nao_burla_a_checagem_da_rota(): void
    {
        $status = \App\Models\StatusProjeto::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->colega)
            ->postJson("/api/leads/{$this->lead->id}/projetos", [
                'nome' => 'Projeto por array',
                'usuario_id' => [$this->lead->id],
                'status_id' => $status->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('projetos', ['nome' => 'Projeto por array']);
    }

    /**
     * As três negativas da criação precisam ser indistinguíveis, senão a
     * diferença revela quais leads existem no tenant.
     *
     * Mesmo reparo do teste irmão em `/api/anotacao`: só `->status()` é o
     * oráculo que a rodada anterior já tinha fechado (os dois casos caem no
     * mesmo 403 de classe), mas o teste não provava que os CORPOS também
     * batem. Comparar o corpo também é o que faz o teste medir "indistinguí-
     * veis" de verdade, e não só "mesmo número de status".
     */
    public function test_criar_projeto_com_lead_inexistente_responde_igual_a_lead_alheio(): void
    {
        $status = StatusProjeto::factory()->create(['tenant_id' => $this->tenant->id]);

        $alheio = $this->actingAs($this->colega)->postJson("/api/leads/{$this->lead->id}/projetos", [
            'nome' => 'A',  'status_id' => $status->id,
        ]);

        $inexistente = $this->actingAs($this->colega)->postJson('/api/leads/999999/projetos', [
            'nome' => 'A',  'status_id' => $status->id,
        ]);

        $this->assertSame(
            $alheio->status().' '.$alheio->getContent(),
            $inexistente->status().' '.$inexistente->getContent(),
            'a diferença de status ou de corpo revelaria quais leads existem',
        );
    }

    /**
     * Item 4: `padroes.*` era a única regra `exists` da base sem filtro de
     * tenant. Um id de modelo de OUTRA empresa passava (201) e um id
     * inexistente era recusado (422) — a diferença responde "esta linha existe
     * em alguma empresa?". O `where('user_id', auth()->id())` do controller já
     * impedia a criação da tarefa; o que vazava era só a informação.
     */
    public function test_aplicar_modelo_nao_distingue_padrao_de_outro_tenant_de_id_inexistente(): void
    {
        $outro = Tenant::factory()->create();
        $staffAlheio = User::factory()->create(['tenant_id' => $outro->id]);
        app(CurrentTenant::class)->set($outro);
        $padraoAlheio = new TarefaPadrao;
        $padraoAlheio->fill(['user_id' => $staffAlheio->id, 'titulo' => 'Alheio', 'prazo_dias' => 3]);
        $padraoAlheio->tenant_id = $outro->id;
        $padraoAlheio->save();
        app(CurrentTenant::class)->set($this->tenant);

        $alheio = $this->actingAs($this->dono)->postJson("/api/leads/{$this->lead->id}/tarefas/aplicar-modelos", [
            'padroes' => [$padraoAlheio->id],
        ]);
        $inexistente = $this->actingAs($this->dono)->postJson("/api/leads/{$this->lead->id}/tarefas/aplicar-modelos", [
            'padroes' => [999999],
        ]);

        $alheio->assertStatus(422);
        $this->assertSame(
            $inexistente->status().' '.$inexistente->getContent(),
            $alheio->status().' '.$alheio->getContent(),
            'a diferenca revelaria que o id existe em outra empresa',
        );
    }

    /** O modelo próprio continua sendo aplicado. */
    public function test_aplicar_modelo_proprio_continua_funcionando(): void
    {
        $meu = new TarefaPadrao;
        $meu->fill(['user_id' => $this->dono->id, 'titulo' => 'Meu modelo', 'prazo_dias' => 2]);
        $meu->tenant_id = $this->tenant->id;
        $meu->save();

        $this->actingAs($this->dono)
            ->postJson("/api/leads/{$this->lead->id}/tarefas/aplicar-modelos", [
                'padroes' => [$meu->id],
            ])
            ->assertCreated();

        $this->assertDatabaseHas('tarefas', ['titulo' => 'Meu modelo', 'usuario_id' => $this->lead->id]);
    }

    /**
     * Item 5: `status_id` era só `required`.
     *
     * O pior efeito não é o vazamento: `Projeto::estadoSeriaPerda()` resolve o
     * status pelo Eloquent, o TenantScope não acha o status de fora, o método
     * devolve `false` e a EXIGÊNCIA DE MOTIVO DE PERDA é contornada. Dava para
     * criar um projeto já perdido, sem motivo, com o id de um status `is_lost`
     * de qualquer outra empresa.
     */
    public function test_status_de_outro_tenant_e_recusado_e_nao_contorna_o_motivo_de_perda(): void
    {
        $outro = Tenant::factory()->create();
        app(CurrentTenant::class)->set($outro);
        $statusAlheioPerdido = StatusProjeto::factory()->create(['tenant_id' => $outro->id, 'is_lost' => true]);
        app(CurrentTenant::class)->set($this->tenant);

        $this->actingAs($this->dono)
            ->postJson("/api/leads/{$this->lead->id}/projetos", [
                'nome' => 'Projeto com status alheio',
                'status_id' => $statusAlheioPerdido->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status_id');

        $this->assertDatabaseMissing('projetos', ['nome' => 'Projeto com status alheio']);
    }

    /** `status_id` inexistente é 422 de validação, e não 500 de violação de FK. */
    public function test_status_inexistente_vira_422_e_nao_500(): void
    {
        $this->actingAs($this->dono)
            ->postJson("/api/leads/{$this->lead->id}/projetos", [
                'nome' => 'Projeto fantasma',  'status_id' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status_id');

        $this->actingAs($this->dono)
            ->putJson("/api/projetos/{$this->projeto->id}", [
                'nome' => 'Projeto renomeado', 'status_id' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status_id');
    }

    /** E o status arquivado continua aceito: `Projeto::status()` usa withTrashed(). */
    public function test_status_arquivado_do_proprio_tenant_continua_aceito(): void
    {
        $arquivado = StatusProjeto::factory()->create(['tenant_id' => $this->tenant->id]);
        $arquivado->delete();

        $this->actingAs($this->dono)
            ->putJson("/api/projetos/{$this->projeto->id}", [
                'nome' => 'Projeto com status arquivado', 'status_id' => $arquivado->id,
            ])
            ->assertOk();
    }

    public function test_dono_cria_projeto_no_proprio_lead(): void
    {
        $status = StatusProjeto::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->dono)
            ->postJson("/api/leads/{$this->lead->id}/projetos", [
                'nome' => 'Projeto legitimo',
                'status_id' => $status->id,
            ])
            ->assertCreated();
    }
}
