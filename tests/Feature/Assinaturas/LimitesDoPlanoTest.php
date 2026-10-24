<?php

namespace Tests\Feature\Assinaturas;

use App\Models\Assinatura;
use App\Models\Convite;
use App\Models\Funil;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Services\Assinaturas\Assinaturas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Os limites do plano valem em todo caminho que acrescenta usuário, lead ou
 * funil — inclusive os que não "criam" nada: convite pendente, aceite de
 * convite, restauração da lixeira e troca de plano.
 */
class LimitesDoPlanoTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['comercial.exigir_assinatura' => true]);
        Mail::fake();
    }

    /** Plano Inicial: até 3 usuários, 1 funil (leads ajustável). */
    private function empresaNoInicial(int $limiteDeLeads = 2000): array
    {
        config(['comercial.planos.0.limites.leads' => $limiteDeLeads]);
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        app(Assinaturas::class)->ativar($tenant, 'inicial', 'mensal');

        return [$tenant, $admin];
    }

    private function convite(Tenant $tenant, User $autor, string $email, string $token, ?\DateTimeInterface $expira = null): Convite
    {
        $c = new Convite(['email' => $email, 'papel' => 'vendedor', 'convidado_por' => $autor->id, 'token_hash' => hash('sha256', $token), 'expira_em' => $expira ?? now()->addDays(7)]);
        $c->tenant_id = $tenant->id;
        $c->save();

        return $c;
    }

    private function aceitar(string $token)
    {
        auth()->logout();

        return $this->post("/convites/{$token}", ['name' => 'Novo Membro', 'password' => 'password', 'password_confirmation' => 'password']);
    }

    public function test_convite_pendente_ocupa_vaga_no_limite_de_usuarios(): void
    {
        [$tenant, $admin] = $this->empresaNoInicial();
        $this->agente($tenant); // 2 ativos

        $this->actingAs($admin)->postJson('/api/equipe/convites', ['email' => 'um@conv.test', 'papel' => 'vendedor'])->assertCreated(); // 3ª vaga
        $this->actingAs($admin)->postJson('/api/equipe/convites', ['email' => 'dois@conv.test', 'papel' => 'vendedor'])
            ->assertStatus(422)->assertJsonPath('limite', 'usuarios')
            ->assertJsonPath('message', fn (string $m) => str_contains($m, '1 convite pendente'));
    }

    public function test_reativar_membro_conta_os_convites_pendentes(): void
    {
        [$tenant, $admin] = $this->empresaNoInicial();
        $this->agente($tenant);
        $inativo = $this->agente($tenant);
        $inativo->forceFill(['ativo' => false])->save();
        $this->convite($tenant, $admin, 'p@conv.test', str_repeat('a', 40)); // 2 ativos + 1 convite = 3

        $this->actingAs($admin)->patchJson("/api/equipe/{$inativo->id}/reativar")->assertStatus(422)->assertJsonPath('limite', 'usuarios');
    }

    public function test_reenviar_convite_expirado_volta_a_ocupar_vaga_e_o_pendente_nao_cobra_de_novo(): void
    {
        [$tenant, $admin] = $this->empresaNoInicial();
        $this->agente($tenant);
        $pendente = $this->convite($tenant, $admin, 'p@conv.test', str_repeat('a', 40)); // 3/3
        $expirado = $this->convite($tenant, $admin, 'e@conv.test', str_repeat('b', 40), now()->subDay());

        $this->actingAs($admin)->postJson("/api/equipe/convites/{$pendente->id}/reenviar")->assertOk();
        $this->actingAs($admin)->postJson("/api/equipe/convites/{$expirado->id}/reenviar")->assertStatus(422)->assertJsonPath('limite', 'usuarios');
    }

    public function test_aceitar_convite_confere_o_limite_de_novo(): void
    {
        [$tenant, $admin] = $this->empresaNoInicial();
        $this->agente($tenant);
        $this->convite($tenant, $admin, 'p@conv.test', $token = str_repeat('c', 40)); // 3/3 com o convite
        $this->agente($tenant); // a vaga foi ocupada por outro caminho: 3 ativos + 1 convite

        $this->aceitar($token)->assertSessionHasErrors('email');
        $this->assertFalse(User::where('email', 'p@conv.test')->exists());
    }

    public function test_aceitar_convite_dentro_do_limite_continua_funcionando(): void
    {
        [$tenant, $admin] = $this->empresaNoInicial();
        $this->agente($tenant);
        $this->convite($tenant, $admin, 'p@conv.test', $token = str_repeat('d', 40));

        $this->aceitar($token)->assertRedirect(route('dashboard'));
        $this->assertSame(3, User::where('tenant_id', $tenant->id)->where('ativo', true)->count());
    }

    public function test_aceitar_convite_e_recusado_quando_a_assinatura_nao_permite_gravar(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $this->convite($tenant, $admin, 'p@conv.test', $token = str_repeat('e', 40));
        app(Assinaturas::class)->da($tenant);
        Assinatura::where('tenant_id', $tenant->id)->update(['teste_termina_em' => now()->subDay()]);

        $this->aceitar($token)->assertSessionHasErrors('email');
        $this->assertFalse(User::where('email', 'p@conv.test')->exists());
    }

    public function test_restaurar_lead_da_lixeira_respeita_o_limite_de_leads(): void
    {
        [$tenant, $admin] = $this->empresaNoInicial(limiteDeLeads: 2);
        $um = $this->lead($tenant, $admin);
        $dois = $this->lead($tenant, $admin);
        $um->delete();
        $dois->delete();
        $this->lead($tenant, $admin);
        $this->lead($tenant, $admin); // 2/2

        $this->actingAs($admin)->patchJson("/api/leads/{$um->id}/restaurar")->assertStatus(422)->assertJsonPath('limite', 'leads');
        $this->actingAs($admin)->postJson('/api/lixeira/restaurar', ['tipo' => 'leads', 'ids' => [$um->id, $dois->id]])->assertStatus(422)->assertJsonPath('limite', 'leads');
        $this->assertSame(2, Usuario::where('tenant_id', $tenant->id)->count());
    }

    public function test_restaurar_funil_arquivado_respeita_o_limite_de_funis(): void
    {
        [$tenant, $admin] = $this->empresaNoInicial();
        $arquivado = Funil::factory()->create(['tenant_id' => $tenant->id]);
        $arquivado->delete();

        $this->actingAs($admin)->patchJson("/api/funis/{$arquivado->id}/restaurar")->assertStatus(422)->assertJsonPath('limite', 'funis');
        $this->assertSame(1, Funil::where('tenant_id', $tenant->id)->count());
    }

    public function test_contratar_plano_exige_que_o_uso_caiba_nele(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        foreach (range(1, 6) as $_) {
            $this->agente($tenant);
        } // 7 usuários no teste, que não tem limite
        app(Assinaturas::class)->da($tenant);

        $this->actingAs($admin)->post('/configuracoes/assinatura', ['plano' => 'inicial', 'ciclo' => 'mensal'])
            ->assertSessionHasErrors(['plano' => 'O plano Inicial permite até 3 usuários ativos e a empresa tem 7. Desative 4 em Configurações › Equipe ou escolha outro plano.']);
        $this->assertSame('teste', Assinatura::where('tenant_id', $tenant->id)->value('status'));
    }

    public function test_pagina_de_assinatura_diz_quais_planos_nao_cabem(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        foreach (range(1, 3) as $_) {
            $this->agente($tenant);
        }

        $this->actingAs($admin)->get('/configuracoes/assinatura')->assertInertia(fn ($page) => $page
            ->where('planos.0.chave', 'inicial')
            ->where('planos.0.excessos.0', fn (string $m) => str_contains($m, 'até 3 usuários ativos e a empresa tem 4'))
            ->where('planos.1.excessos', []));
    }
}
