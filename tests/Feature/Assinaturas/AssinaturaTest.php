<?php

namespace Tests\Feature\Assinaturas;

use App\Models\Assinatura;
use App\Models\Funil;
use App\Models\User;
use App\Services\Assinaturas\Assinaturas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Planos de assinatura: toda empresa nasce em teste (14 dias, todos os
 * recursos); escolhe um plano com ciclo mensal ou anual; limites do plano
 * valem para usuários ativos, funis e leads; teste expirado, plano vencido
 * ou cancelado bloqueia escrita e deixa leitura e a troca de plano.
 */
class AssinaturaTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // A suíte roda com a exigência desligada (phpunit.xml); aqui ela é o assunto.
        config(['comercial.exigir_assinatura' => true]);
    }

    public function test_cadastro_cria_assinatura_em_teste_com_o_plano_pretendido(): void
    {
        Carbon::setTestNow('2026-10-22 10:00:00');

        $this->post('/register', ['name' => 'Ana', 'empresa' => 'Acme', 'email' => 'ana@ex.test', 'password' => 'password', 'password_confirmation' => 'password', 'plano' => 'profissional']);

        $assinatura = Assinatura::where('tenant_id', User::firstWhere('email', 'ana@ex.test')->tenant_id)->first();
        $this->assertSame('teste', $assinatura->status);
        $this->assertSame('profissional', $assinatura->plano);
        $this->assertSame('2026-11-05', $assinatura->teste_termina_em->toDateString());
    }

    public function test_props_compartilhadas_trazem_a_assinatura_com_uso_e_limites(): void
    {
        Carbon::setTestNow('2026-10-22 10:00:00');
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        app(Assinaturas::class)->iniciarTeste($tenant, 'inicial');

        $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('assinatura.status', 'teste')
            ->where('assinatura.plano', 'inicial')
            ->where('assinatura.dias_restantes', 14)
            ->where('assinatura.bloqueada', false)
            ->where('assinatura.limites.usuarios', null) // no teste, tudo liberado
            ->where('assinatura.uso.usuarios', 1));
    }

    public function test_empresa_sem_assinatura_ganha_teste_na_primeira_requisicao(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->assertSame('teste', Assinatura::where('tenant_id', $tenant->id)->value('status'));
    }

    public function test_teste_expirado_bloqueia_escrita_e_libera_leitura_e_escolha_de_plano(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $lead = $this->lead($tenant, $admin);
        Assinatura::create(['tenant_id' => $tenant->id, 'status' => 'teste', 'plano' => 'inicial', 'teste_termina_em' => now()->subDay()]);

        $this->actingAs($admin)->getJson('/api/leads')->assertOk();
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page->where('assinatura.bloqueada', true));

        $this->actingAs($admin)->postJson("/api/leads/{$lead->id}/tarefas", ['titulo' => 'x', 'data_limite' => today()->toDateString()])
            ->assertStatus(402)->assertJsonPath('assinatura', 'bloqueada')->assertJsonStructure(['message']);
        $this->actingAs($admin)->post('/leads', ['nome' => 'Novo Lead', 'email' => 'n@ex.test'])->assertRedirect()->assertSessionHas('erro');
        $this->actingAs($admin)->patch('/profile', ['name' => 'Admin Novo', 'email' => $admin->email])->assertRedirect('/profile');

        $this->actingAs($admin)->post('/configuracoes/assinatura', ['plano' => 'profissional', 'ciclo' => 'anual'])->assertRedirect('/configuracoes/assinatura');
        $a = Assinatura::where('tenant_id', $tenant->id)->first();
        $this->assertSame('ativa', $a->status);
        $this->assertSame('anual', $a->ciclo);
        $this->assertSame(now()->addYear()->toDateString(), $a->vigente_ate->toDateString());
        $this->actingAs($admin)->postJson("/api/leads/{$lead->id}/tarefas", ['titulo' => 'x', 'data_limite' => today()->toDateString()])->assertCreated();
    }

    public function test_plano_vencido_ou_cancelado_bloqueia(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $lead = $this->lead($tenant, $admin);
        $a = Assinatura::create(['tenant_id' => $tenant->id, 'status' => 'ativa', 'plano' => 'profissional', 'ciclo' => 'mensal', 'teste_termina_em' => now()->subMonths(2), 'vigente_ate' => now()->subDay()]);

        $this->assertSame('vencida', $a->fresh()->situacao());
        $this->actingAs($admin)->postJson("/api/leads/{$lead->id}/tarefas", ['titulo' => 'x', 'data_limite' => today()->toDateString()])->assertStatus(402);

        app(Assinaturas::class)->cancelar($tenant);
        $this->assertSame('cancelada', $a->fresh()->situacao());
        $this->actingAs($admin)->postJson("/api/leads/{$lead->id}/tarefas", ['titulo' => 'x', 'data_limite' => today()->toDateString()])->assertStatus(402);
    }

    public function test_limites_do_plano_inicial_e_sem_limite_no_profissional(): void
    {
        \Illuminate\Support\Facades\Mail::fake();
        config(['comercial.planos.0.limites.leads' => 2]);
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $this->agente($tenant);
        $this->agente($tenant);
        Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        app(Assinaturas::class)->ativar($tenant, 'inicial', 'mensal');

        // 3 usuários ativos: o 4º convite é recusado.
        $this->actingAs($admin)->postJson('/api/equipe/convites', ['email' => 'quarto@ex.test', 'papel' => 'vendedor'])
            ->assertStatus(422)->assertJsonPath('limite', 'usuarios');
        // 1 funil: o 2º é recusado.
        $this->actingAs($admin)->postJson('/api/funis', ['nome' => 'Segundo funil'])->assertStatus(422)->assertJsonPath('limite', 'funis');
        // 2 leads: o 3º é recusado, na API e na importação.
        $this->lead($tenant, $admin);
        $this->lead($tenant, $admin);
        $this->actingAs($admin)->postJson('/api/leads', ['nome' => 'Terceiro Lead', 'email' => 't@ex.test'])->assertStatus(422)->assertJsonPath('limite', 'leads');
        $this->actingAs($admin)->post('/leads', ['nome' => 'Terceiro Lead', 'email' => 't@ex.test'])->assertRedirect()->assertSessionHas('erro');

        app(Assinaturas::class)->ativar($tenant, 'profissional', 'mensal');
        $this->actingAs($admin)->postJson('/api/leads', ['nome' => 'Terceiro Lead', 'email' => 't@ex.test'])->assertCreated();
        $this->actingAs($admin)->postJson('/api/equipe/convites', ['email' => 'quarto@ex.test', 'papel' => 'vendedor'])->assertCreated();
    }

    public function test_pagina_de_assinatura_so_para_quem_gerencia_e_recusa_plano_invalido(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $vendedor = $this->agente($tenant);

        $this->actingAs($admin)->get('/configuracoes/assinatura')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Configuracoes/Assinatura')
            ->has('planos', 3)
            ->where('assinatura.status', 'teste'));
        $this->actingAs($vendedor)->get('/configuracoes/assinatura')->assertForbidden();
        $this->actingAs($admin)->post('/configuracoes/assinatura', ['plano' => 'ouro', 'ciclo' => 'mensal'])->assertSessionHasErrors('plano');
    }

    public function test_comando_ativa_ou_renova_manualmente(): void
    {
        $tenant = $this->novoTenant();
        $this->agente($tenant, 'admin');

        $this->artisan('assinatura:ativar', ['tenant' => $tenant->id, 'plano' => 'empresa', '--ciclo' => 'anual'])->assertSuccessful();
        $a = Assinatura::where('tenant_id', $tenant->id)->first();
        $this->assertSame('empresa', $a->plano);
        $this->assertSame(now()->addYear()->toDateString(), $a->vigente_ate->toDateString());

        // Renovar antes do fim soma ao vencimento atual, não a hoje.
        $this->artisan('assinatura:ativar', ['tenant' => $tenant->id, 'plano' => 'empresa', '--ciclo' => 'mensal'])->assertSuccessful();
        $this->assertSame(now()->addYear()->addMonthNoOverflow()->toDateString(), $a->fresh()->vigente_ate->toDateString());
    }
}
