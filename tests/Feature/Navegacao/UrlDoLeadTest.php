<?php

namespace Tests\Feature\Navegacao;

use App\Models\Funil;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O lead tem URL própria.
 *
 * Antes o id viajava em `sessionStorage` e a rota era `/perfilUsuario`, sem
 * identificador. O que isso impedia, na prática: mandar o link de um lead
 * para um colega, abrir dois leads em abas para comparar, e confiar no botão
 * voltar. Num CRM, "me manda esse lead" é conversa de todo dia.
 *
 * O teste fixa as duas propriedades que a URL precisa ter: ela identifica o
 * lead, e ela não atravessa a fronteira do tenant.
 */
class UrlDoLeadTest extends TestCase
{
    use RefreshDatabase;

    private function cenario(): array
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        app(CurrentTenant::class)->set($tenant);

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $lead = Usuario::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $staff->id,
            'funil_id' => $funil->id,
        ]);

        return [$tenant, $staff, $lead];
    }

    public function test_a_url_carrega_o_lead_que_ela_nomeia(): void
    {
        [, $staff, $lead] = $this->cenario();

        $this->actingAs($staff)
            ->get("/leads/{$lead->id}")
            ->assertOk();
    }

    /**
     * A prop é o que substitui o sessionStorage: sem ela chegando na página,
     * o perfil abriria vazio e o link continuaria não servindo para nada.
     */
    public function test_o_id_do_lead_chega_na_pagina(): void
    {
        [, $staff, $lead] = $this->cenario();

        $resposta = $this->actingAs($staff)->get("/leads/{$lead->id}");

        $resposta->assertOk();
        $this->assertSame(
            $lead->id,
            $resposta->viewData('page')['props']['leadId'],
            'a página precisa receber leadId para saber qual lead abrir',
        );
    }

    public function test_lead_de_outro_tenant_devolve_404(): void
    {
        [, $staff] = $this->cenario();

        $outroTenant = Tenant::factory()->create();
        $outroStaff = User::factory()->create(['tenant_id' => $outroTenant->id]);
        app(CurrentTenant::class)->set($outroTenant);
        $funilAlheio = Funil::factory()->create(['tenant_id' => $outroTenant->id]);
        $leadAlheio = Usuario::factory()->create([
            'tenant_id' => $outroTenant->id,
            'user_id' => $outroStaff->id,
            'funil_id' => $funilAlheio->id,
        ]);

        $this->actingAs($staff)
            ->get("/leads/{$leadAlheio->id}")
            ->assertNotFound();
    }

    /**
     * A trava contra a regressão que os outros testes mascaravam.
     *
     * `CurrentTenant` é singleton, e o setUp dos casos de teste o deixa
     * preenchido — então uma requisição de teste encontra um tenant ativo que
     * uma requisição de produção não teria. Foi isso que escondeu, por duas
     * entregas, o fato de o route model binding rodar ANTES do middleware
     * `tenant`: o TenantScope é fail-closed e devolvia 500 em toda rota com
     * parâmetro de model.
     *
     * Limpar o tenant aqui reproduz a condição real. Se alguém desfizer o
     * prependToPriorityList em bootstrap/app.php, este teste cai — e os
     * outros continuariam passando.
     */
    public function test_binding_funciona_sem_tenant_pre_carregado(): void
    {
        [, $staff, $lead] = $this->cenario();

        app(CurrentTenant::class)->clear();

        $this->actingAs($staff)
            ->get("/leads/{$lead->id}")
            ->assertOk();
    }

    /**
     * A mesma condição nas rotas de API com parâmetro de model, que são as
     * que já estavam no ar com o defeito.
     */
    public function test_rotas_de_api_com_model_tambem_resolvem_sem_tenant_pre_carregado(): void
    {
        [$tenant, $staff] = $this->cenario();

        $funil = Funil::factory()->create(['tenant_id' => $tenant->id]);

        setPermissionsTeamId($tenant->id);
        $staff->assignRole('admin');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        app(CurrentTenant::class)->clear();

        $this->actingAs($staff)
            ->putJson("/api/funis/{$funil->id}", ['nome' => 'Renomeado'])
            ->assertOk();
    }

    public function test_a_rota_antiga_sem_identificador_nao_existe_mais(): void
    {
        [, $staff] = $this->cenario();

        $this->actingAs($staff)->get('/perfilUsuario')->assertNotFound();
    }
}
