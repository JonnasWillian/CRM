<?php

namespace Tests\Feature\Autorizacao;

use App\Models\Funil;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UsuarioPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $gestor;
    private User $vendedor;
    private User $semPapel;
    private Usuario $leadDoGestor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->gestor = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->vendedor = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->semPapel = User::factory()->create(['tenant_id' => $this->tenant->id]);

        app(CurrentTenant::class)->set($this->tenant);
        setPermissionsTeamId($this->tenant->id);
        $this->gestor->assignRole('gestor');
        $this->vendedor->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
        $this->leadDoGestor = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->gestor->id, 'funil_id' => $funil->id,
        ]);
    }

    public function test_vendedor_nao_alcanca_lead_de_colega(): void
    {
        $this->actingAs($this->vendedor)
            ->getJson("/api/usuarioPerfil/{$this->leadDoGestor->id}")
            ->assertNotFound();
    }

    /** Review Focus 1: o erro mais fácil é trancar o gestor para fora da equipe dele. */
    public function test_gestor_alcanca_lead_de_outro_agente_do_tenant(): void
    {
        $outro = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $funil = Funil::factory()->create(['tenant_id' => $this->tenant->id]);
        $lead = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $outro->id, 'funil_id' => $funil->id,
        ]);

        $this->actingAs($this->gestor)
            ->getJson("/api/usuarioPerfil/{$lead->id}")
            ->assertOk();
    }

    /** Review Focus 2: quem não recebeu papel nenhum não pode virar erro. */
    public function test_usuario_sem_papel_recebe_negativa_limpa(): void
    {
        $this->actingAs($this->semPapel)
            ->getJson("/api/usuarioPerfil/{$this->leadDoGestor->id}")
            ->assertNotFound();
    }

    public function test_dono_edita_o_proprio_lead(): void
    {
        $this->actingAs($this->gestor)
            ->putJson("/api/usuarios/{$this->leadDoGestor->id}", [
                'nome' => 'Nome Editado',
                'email' => $this->leadDoGestor->email,
                'telefone' => '11999998888',
            ])
            ->assertOk();
    }

    public function test_vendedor_nao_edita_lead_de_colega(): void
    {
        $this->actingAs($this->vendedor)
            ->putJson("/api/usuarios/{$this->leadDoGestor->id}", [
                'nome' => 'Sequestrado',
                'email' => $this->leadDoGestor->email,
                'telefone' => '11999998888',
            ])
            ->assertNotFound();

        $this->assertSame($this->leadDoGestor->nome, $this->leadDoGestor->fresh()->nome);
    }

    /**
     * O check provisório em LeadAtividadeController comparava user_id com
     * auth()->id() e portanto trancava o gestor para fora da equipe dele.
     * A policy corrige esse comportamento, que está em produção.
     */
    public function test_gestor_ve_atividades_de_lead_da_equipe(): void
    {
        $outro = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $funil = Funil::factory()->create(['tenant_id' => $this->tenant->id]);
        $lead = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $outro->id, 'funil_id' => $funil->id,
        ]);

        $this->actingAs($this->gestor)
            ->getJson("/api/leads/{$lead->id}/atividades")
            ->assertOk();
    }

    /**
     * O oráculo que a validação abria: sem autorizar antes de validar, um
     * payload malformado contra lead alheio devolvia 422 e um payload válido
     * devolvia 404 — a diferença revela que o id existe no tenant.
     */
    public function test_payload_invalido_contra_lead_alheio_devolve_404_e_nao_422(): void
    {
        $this->actingAs($this->vendedor)
            ->putJson("/api/usuarios/{$this->leadDoGestor->id}", ['nome' => ''])
            ->assertNotFound();
    }
}
