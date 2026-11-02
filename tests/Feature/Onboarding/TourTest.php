<?php

namespace Tests\Feature\Onboarding;

use App\Mail\ConviteDeEquipeMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/** Estado do passo a passo de cada usuário: iniciar, avançar, concluir, cancelar e refazer. */
class TourTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private function tour(User $u, array $dados)
    {
        return $this->actingAs($u)->patchJson(route('api.tour.update'), $dados);
    }

    public function test_iniciar_avancar_e_concluir(): void
    {
        $u = $this->agente($this->novoTenant());

        $this->tour($u, ['acao' => 'iniciar', 'passo' => 'novo-lead'])->assertOk()
            ->assertJson(['tour_estado' => 'em_andamento', 'tour_versao' => 'completa', 'tour_passo' => 'novo-lead']);
        $this->tour($u, ['acao' => 'avancar', 'passo' => 'lead-nome'])->assertOk()->assertJsonPath('tour_passo', 'lead-nome');
        $this->tour($u, ['acao' => 'concluir'])->assertOk()->assertJson(['tour_estado' => 'concluido', 'tour_passo' => null]);

        $this->assertSame('concluido', $u->fresh()->tour_estado);
    }

    public function test_cancelar_limpa_o_passo(): void
    {
        $u = $this->agente($this->novoTenant());
        $this->tour($u, ['acao' => 'iniciar', 'passo' => 'novo-lead']);

        $this->tour($u, ['acao' => 'cancelar'])->assertOk()->assertJson(['tour_estado' => 'cancelado', 'tour_passo' => null]);
    }

    public function test_refazer_usa_a_versao_curta(): void
    {
        $u = $this->agente($this->novoTenant());
        $u->forceFill(['tour_estado' => 'concluido', 'tour_versao' => 'completa'])->save();

        $this->tour($u, ['acao' => 'refazer', 'passo' => 'novo-lead'])->assertOk()
            ->assertJson(['tour_estado' => 'em_andamento', 'tour_versao' => 'curta', 'tour_passo' => 'novo-lead']);
    }

    public function test_passo_invalido_e_recusado(): void
    {
        $u = $this->agente($this->novoTenant());

        $this->tour($u, ['acao' => 'iniciar', 'passo' => 'Passo Com Espaço'])->assertUnprocessable()->assertJsonValidationErrors('passo');
        $this->tour($u, ['acao' => 'avancar'])->assertUnprocessable()->assertJsonValidationErrors('passo');
        $this->tour($u, ['acao' => 'explodir'])->assertUnprocessable()->assertJsonValidationErrors('acao');
        $this->assertSame('pendente', $u->fresh()->tour_estado);
    }

    public function test_so_altera_o_proprio_usuario(): void
    {
        $t = $this->novoTenant();
        $ana = $this->agente($t);
        $bia = $this->agente($t);

        $this->tour($ana, ['acao' => 'cancelar'])->assertOk();

        $this->assertSame('cancelado', $ana->fresh()->tour_estado);
        $this->assertSame('pendente', $bia->fresh()->tour_estado);
    }

    public function test_convite_aceito_comeca_tour_curto_pendente(): void
    {
        Mail::fake();
        $t = $this->novoTenant();
        $admin = $this->agente($t, 'admin');
        $this->actingAs($admin)->postJson('/api/equipe/convites', ['email' => 'nova@exemplo.test', 'papel' => 'vendedor'])->assertCreated();
        $token = null;
        Mail::assertQueued(ConviteDeEquipeMail::class, function (ConviteDeEquipeMail $m) use (&$token) {
            $token = $m->token;

            return true;
        });
        auth()->logout();

        $this->post("/convites/{$token}", ['name' => 'Nova Pessoa', 'password' => 'SenhaForte#2026', 'password_confirmation' => 'SenhaForte#2026'])
            ->assertRedirect('/dashboard')
            ->assertSessionMissing('sucesso');

        $nova = User::firstWhere('email', 'nova@exemplo.test');
        $this->assertSame('pendente', $nova->tour_estado);
        $this->assertSame('curta', $nova->tour_versao);
    }
}
