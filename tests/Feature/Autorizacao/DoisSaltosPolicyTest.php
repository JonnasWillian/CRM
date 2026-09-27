<?php

namespace Tests\Feature\Autorizacao;

use App\Models\Funil;
use App\Models\Projeto;
use App\Models\ProjetoAnexo;
use App\Models\ProjetoAnotacao;
use App\Models\Statu;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Dois saltos: anexo/anotação -> projeto -> usuario.user_id.
 *
 * `/projetoAnotacao/{id}` é a armadilha documentada no spec: no GET o id é do
 * PROJETO, no PUT e no DELETE é da própria anotação. O binding resolve pelo
 * nome do parâmetro, então cada verbo declara o seu e a URL não muda.
 */
class DoisSaltosPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $dono;
    private User $intruso;
    private Projeto $projeto;
    private ProjetoAnotacao $anotacao;
    private ProjetoAnexo $anexo;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $this->dono = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->intruso = User::factory()->create(['tenant_id' => $tenant->id]);

        app(CurrentTenant::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
        $this->dono->assignRole('vendedor');
        $this->intruso->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $lead = Usuario::factory()->create([
            'tenant_id' => $tenant->id, 'user_id' => $this->dono->id, 'funil_id' => $funil->id,
        ]);
        $status = Statu::factory()->create(['tenant_id' => $tenant->id]);

        $this->projeto = new Projeto();
        $this->projeto->fill(['nome' => 'Proposta', 'usuario_id' => $lead->id, 'status_id' => $status->id]);
        $this->projeto->tenant_id = $tenant->id;
        $this->projeto->save();

        $this->anotacao = ProjetoAnotacao::create(['descricao' => 'Escopo', 'projeto_id' => $this->projeto->id]);
        $this->anexo = ProjetoAnexo::create(['nome' => 'contrato.pdf', 'local' => 'a/c.pdf', 'projeto_id' => $this->projeto->id]);
    }

    /** Review Focus 5: no GET o id é do projeto. */
    public function test_get_de_anotacao_autoriza_o_projeto(): void
    {
        $this->actingAs($this->intruso)->getJson("/api/projetoAnotacao/{$this->projeto->id}")->assertNotFound();
        $this->actingAs($this->dono)->getJson("/api/projetoAnotacao/{$this->projeto->id}")->assertOk();
    }

    /** No PUT e no DELETE o id é da própria anotação — mesma URL, outro model. */
    public function test_delete_de_anotacao_autoriza_a_anotacao(): void
    {
        $this->actingAs($this->intruso)->deleteJson("/api/projetoAnotacao/{$this->anotacao->id}")->assertNotFound();
        $this->assertDatabaseHas('projetoAnotacaos', ['id' => $this->anotacao->id, 'deleted_at' => null]);
    }

    public function test_delete_de_anexo_autoriza_o_anexo(): void
    {
        $this->actingAs($this->intruso)->deleteJson("/api/projetoAnexo/{$this->anexo->id}")->assertNotFound();

        // O teste irmão de anotação já verifica isto; faltava aqui —
        // assertNotFound() sozinho prova a negativa, não que o anexo
        // sobreviveu a ela.
        $this->assertDatabaseHas('projetoAnexos', ['id' => $this->anexo->id, 'deleted_at' => null]);
    }

    /** Review Focus 4: linha soft-deleted some pelo binding, e isso é o desejado. */
    public function test_anotacao_apagada_responde_404_para_o_proprio_dono(): void
    {
        $this->anotacao->delete();

        $this->actingAs($this->dono)
            ->deleteJson("/api/projetoAnotacao/{$this->anotacao->id}")
            ->assertNotFound();
    }
}
