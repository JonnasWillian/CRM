<?php

namespace Tests\Feature\Contas;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * P3: excluir a própria conta não pode apagar a carteira da empresa nem
 * deixá-la sem admin.
 */
class ExclusaoDeContaTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->novoTenant();
        // Um admin fixo, para que "último admin" só apareça onde o teste quer.
        $this->agente($this->tenant, 'admin');
    }

    private function excluir($user)
    {
        return $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => 'password']);
    }

    public function test_vendedor_com_leads_nao_exclui_e_os_leads_ficam(): void
    {
        $vendedor = $this->agente($this->tenant);
        $lead = $this->lead($this->tenant, $vendedor);

        $this->excluir($vendedor)->assertSessionHasErrors('conta')->assertRedirect('/profile');

        $this->assertNotNull($vendedor->fresh());
        $this->assertDatabaseHas('usuarios', ['id' => $lead->id, 'deleted_at' => null]);
        $this->assertAuthenticatedAs($vendedor);
    }

    public function test_leads_na_lixeira_tambem_impedem(): void
    {
        $vendedor = $this->agente($this->tenant);
        $this->lead($this->tenant, $vendedor)->delete();

        $this->excluir($vendedor)->assertSessionHasErrors('conta');
        $this->assertNotNull($vendedor->fresh());
    }

    public function test_ultimo_admin_nao_exclui(): void
    {
        $outro = $this->novoTenant();
        $unicoAdmin = $this->agente($outro, 'admin');

        $this->excluir($unicoAdmin)->assertSessionHasErrors('conta');
        $this->assertNotNull($unicoAdmin->fresh());
    }

    public function test_admin_com_outro_admin_e_sem_leads_exclui(): void
    {
        $segundoAdmin = $this->agente($this->tenant, 'admin');

        $this->excluir($segundoAdmin)->assertSessionHasNoErrors()->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($segundoAdmin->fresh());
    }

    public function test_vendedor_sem_leads_exclui(): void
    {
        $vendedor = $this->agente($this->tenant);

        $this->excluir($vendedor)->assertSessionHasNoErrors()->assertRedirect('/');
        $this->assertNull($vendedor->fresh());
    }

    public function test_senha_errada_continua_sendo_checada_antes(): void
    {
        $vendedor = $this->agente($this->tenant);

        $this->actingAs($vendedor)->from('/profile')->delete('/profile', ['password' => 'errada'])
            ->assertSessionHasErrors('password')
            ->assertSessionDoesntHaveErrors('conta');
    }

    public function test_tela_de_perfil_ja_informa_o_impedimento(): void
    {
        $vendedor = $this->agente($this->tenant);
        $this->lead($this->tenant, $vendedor);

        $this->actingAs($vendedor)->get('/profile')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Profile/Edit')
                ->has('impedimentosDeExclusao', 1));
    }
}
