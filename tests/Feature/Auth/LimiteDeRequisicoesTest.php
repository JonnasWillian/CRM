<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class LimiteDeRequisicoesTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    public function test_api_limita_por_usuario(): void
    {
        $agente = $this->agente($this->novoTenant());

        for ($i = 0; $i < 120; $i++) {
            $this->actingAs($agente)->getJson('/api/funis')->assertOk();
        }

        $this->actingAs($agente)->getJson('/api/funis')->assertTooManyRequests();
    }

    /**
     * O limite por email+IP do LoginRequest não pega quem troca de email a
     * cada tentativa. 30/min e não 10: um escritório atrás de NAT divide o IP.
     */
    public function test_login_limita_por_ip_mesmo_trocando_de_email(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->post('/login', ['email' => "tentativa{$i}@exemplo.com", 'password' => 'errada'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'mais-uma@exemplo.com', 'password' => 'errada'])
            ->assertTooManyRequests();
    }

    public function test_pedido_de_redefinicao_de_senha_limita_por_ip(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/forgot-password', ['email' => "alguem{$i}@exemplo.com"]);
        }

        $this->post('/forgot-password', ['email' => 'mais@exemplo.com'])->assertTooManyRequests();
    }
}
