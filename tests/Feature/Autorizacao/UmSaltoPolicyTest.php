<?php

namespace Tests\Feature\Autorizacao;

use App\Models\Anotacao;
use App\Models\arquivo;
use App\Models\Funil;
use App\Models\Projeto;
use App\Models\Statu;
use App\Models\Tarefa;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Projeto, Tarefa, Anotacao e Arquivo não têm dono próprio: o dono é o do lead
 * a que pertencem. Um salto até `usuarios.user_id`.
 */
class UmSaltoPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $dono;
    private User $intruso;
    private Usuario $lead;
    private Projeto $projeto;
    private Tarefa $tarefa;
    private Anotacao $anotacao;
    private arquivo $arquivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->dono = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->intruso = User::factory()->create(['tenant_id' => $this->tenant->id]);

        app(CurrentTenant::class)->set($this->tenant);
        setPermissionsTeamId($this->tenant->id);
        $this->dono->assignRole('vendedor');
        $this->intruso->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
        $this->lead = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->dono->id, 'funil_id' => $funil->id,
        ]);

        $status = Statu::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->projeto = new Projeto();
        $this->projeto->fill(['nome' => 'Proposta inicial', 'usuario_id' => $this->lead->id, 'status_id' => $status->id]);
        $this->projeto->tenant_id = $this->tenant->id;
        $this->projeto->save();

        $this->tarefa = Tarefa::create(['usuario_id' => $this->lead->id, 'titulo' => 'Ligar', 'data_limite' => now()->addDay()]);
        $this->anotacao = Anotacao::create(['descricao' => 'Primeiro contato', 'usuario_id' => $this->lead->id]);
        $this->arquivo = arquivo::create(['nome' => 'rg.pdf', 'local' => 'arquivos/rg.pdf', 'usuario_id' => $this->lead->id]);
    }

    public function test_intruso_nao_alcanca_projeto_de_lead_alheio(): void
    {
        $this->actingAs($this->intruso)->getJson("/api/projeto/{$this->projeto->id}")->assertNotFound();
        $this->actingAs($this->dono)->getJson("/api/projeto/{$this->projeto->id}")->assertOk();
    }

    public function test_intruso_nao_apaga_tarefa_de_lead_alheio(): void
    {
        $this->actingAs($this->intruso)->deleteJson("/api/tarefas/{$this->tarefa->id}")->assertNotFound();
        $this->assertDatabaseHas('tarefas', ['id' => $this->tarefa->id, 'deleted_at' => null]);
    }

    public function test_intruso_nao_apaga_anotacao_de_lead_alheio(): void
    {
        $this->actingAs($this->intruso)->deleteJson("/api/anotacao/{$this->anotacao->id}")->assertNotFound();
    }

    public function test_intruso_nao_apaga_arquivo_de_lead_alheio(): void
    {
        $this->actingAs($this->intruso)->deleteJson("/api/arquivos/{$this->arquivo->id}")->assertNotFound();
    }

    /**
     * Item 2 da rodada B: `Route::apiResource('arquivos', arquivo::class)`
     * registrava GET /api/arquivos/{arquivo} apontando para `arquivo::show()`,
     * que não existe e nunca existiu no controller — toda chamada estourava
     * "Call to undefined method" e virava 500.
     *
     * Depois do `->only(['index', 'store', 'destroy'])`, o padrão de URI
     * `arquivos/{arquivo}` continua batendo (é o mesmo do DELETE), então o
     * roteador responde 405 (método não permitido) em vez de 404 — mas o
     * ponto do teste é o mesmo: nunca mais 500 por método inexistente.
     */
    public function test_rota_get_arquivos_id_nao_da_mais_500(): void
    {
        $this->actingAs($this->dono)
            ->getJson("/api/arquivos/{$this->arquivo->id}")
            ->assertStatus(405);
    }

    /**
     * Review Focus 5: neste endpoint o `{id}` é o id do LEAD, não da tarefa.
     * Autorizar a entidade errada aqui abriria o buraco em vez de fechá-lo.
     */
    public function test_listar_tarefas_autoriza_o_lead_e_nao_a_tarefa(): void
    {
        $this->actingAs($this->intruso)->getJson("/api/tarefas/{$this->lead->id}")->assertNotFound();
        $this->actingAs($this->dono)->getJson("/api/tarefas/{$this->lead->id}")->assertOk();
    }

    /**
     * Sem autorizar antes de validar, payload malformado contra projeto alheio
     * devolveria 422 e payload válido 404 — a diferença revela que o id existe.
     */
    public function test_payload_invalido_contra_projeto_alheio_devolve_404_e_nao_422(): void
    {
        $this->actingAs($this->intruso)
            ->putJson("/api/projeto/{$this->projeto->id}", ['nome' => ''])
            ->assertNotFound();
    }

    /**
     * O pior dos IDOR desta base: o dono vinha do CORPO da requisição, então
     * nem era preciso adivinhar um id — bastava enviar o do colega.
     */
    public function test_buscar_arquivo_nao_entrega_anexos_de_lead_alheio(): void
    {
        $this->actingAs($this->intruso)
            ->getJson('/api/arquivos?usuario_id='.$this->lead->id)
            ->assertNotFound();

        $this->actingAs($this->dono)
            ->getJson('/api/arquivos?usuario_id='.$this->lead->id)
            ->assertOk();
    }
}
