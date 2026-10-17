<?php

namespace Tests\Feature\Equipe;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Membro desativado não entra e, se já estava logado, perde o acesso na
 * próxima requisição. A mensagem de login é a genérica: não revela que a
 * conta existe e foi desativada.
 */
class ContaDesativadaTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_desativado_nao_consegue_entrar(): void
    {
        $tenant = $this->novoTenant();
        $inativo = $this->agente($tenant);
        $inativo->forceFill(['ativo' => false, 'desativado_em' => now()])->save();

        $this->post('/login', ['email' => $inativo->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_sessao_aberta_de_desativado_recebe_403(): void
    {
        $tenant = $this->novoTenant();
        $inativo = $this->agente($tenant);
        $inativo->forceFill(['ativo' => false])->save();

        $this->actingAs($inativo)->get('/dashboard')->assertForbidden();
        $this->actingAs($inativo)->getJson('/api/leads')->assertForbidden();
    }

    public function test_ativo_segue_entrando_normalmente(): void
    {
        $tenant = $this->novoTenant();
        $ativo = $this->agente($tenant);

        $this->post('/login', ['email' => $ativo->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($ativo);
        $this->assertTrue(User::findOrFail($ativo->id)->ativo);
    }
}
