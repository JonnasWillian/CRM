<?php

namespace Tests\Feature\Assinaturas;

use App\Models\Assinatura;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Assinaturas\Assinaturas;
use App\Services\Assinaturas\Faturamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Plano pago vencido: 3 dias de carência gravando; depois só leitura.
 * Trinta dias em só leitura (teste encerrado, vencida ou cancelada): acesso
 * suspenso, exceto planos, faturas, exportação, perfil e sair.
 */
class CarenciaESuspensaoTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['comercial.exigir_assinatura' => true]);
        Mail::fake();
        Carbon::setTestNow('2026-10-24 10:00:00');
        $this->tenant = $this->novoTenant();
        $this->admin = $this->agente($this->tenant, 'admin');
    }

    private function planoVencidoHa(int $dias): Assinatura
    {
        return Assinatura::create(['tenant_id' => $this->tenant->id, 'status' => 'ativa', 'plano' => 'profissional', 'ciclo' => 'mensal', 'teste_termina_em' => now()->subMonths(3), 'vigente_ate' => now()->subDays($dias)]);
    }

    private function periodoDeTesteEncerradoHa(int $dias): Assinatura
    {
        return Assinatura::create(['tenant_id' => $this->tenant->id, 'status' => 'teste', 'plano' => 'inicial', 'teste_termina_em' => now()->subDays($dias)]);
    }

    private function gravar()
    {
        $lead = $this->lead($this->tenant, $this->admin);

        return $this->actingAs($this->admin)->postJson("/api/leads/{$lead->id}/tarefas", ['titulo' => 'x', 'data_limite' => today()->toDateString()]);
    }

    public function test_plano_pago_vencido_ainda_grava_durante_a_carencia(): void
    {
        $a = $this->planoVencidoHa(1);

        $this->assertSame('carencia', $a->situacao());
        $this->assertFalse($a->bloqueada());
        $this->gravar()->assertCreated();
        $this->actingAs($this->admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page
            ->where('assinatura.status', 'carencia')
            ->where('assinatura.carencia_ate', '2026-10-26')
            ->where('assinatura.dias_restantes', 2));
    }

    public function test_depois_da_carencia_fica_so_leitura_com_data_da_suspensao(): void
    {
        $a = $this->planoVencidoHa(4); // venceu 20/10, carência até 23/10

        $this->assertSame('vencida', $a->situacao());
        $this->gravar()->assertStatus(402);
        $this->actingAs($this->admin)->get('/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('assinatura.somente_leitura_desde', '2026-10-23')
            ->where('assinatura.suspende_em', '2026-11-22')
            ->where('assinatura.suspensa', false));
    }

    public function test_teste_encerrado_nao_tem_carencia(): void
    {
        $this->periodoDeTesteEncerradoHa(1);

        $this->gravar()->assertStatus(402);
    }

    public function test_renovacao_paga_na_carencia_continua_do_vencimento(): void
    {
        $this->planoVencidoHa(2); // venceu 22/10
        $fatura = app(Faturamento::class)->gerar($this->tenant, 'profissional', 'mensal', 'renovacao');

        app(Faturamento::class)->pagar($fatura);

        $this->assertSame('2026-11-22', Assinatura::where('tenant_id', $this->tenant->id)->first()->vigente_ate->toDateString());
    }

    public function test_renovacao_paga_depois_da_carencia_conta_a_partir_do_pagamento(): void
    {
        $this->planoVencidoHa(10);
        $fatura = app(Faturamento::class)->gerar($this->tenant, 'profissional', 'mensal', 'renovacao');

        app(Faturamento::class)->pagar($fatura);

        $this->assertSame('2026-11-24', Assinatura::where('tenant_id', $this->tenant->id)->first()->vigente_ate->toDateString());
    }

    public function test_trinta_dias_em_so_leitura_suspende_o_acesso(): void
    {
        $this->periodoDeTesteEncerradoHa(31);
        $vendedor = $this->agente($this->tenant);

        $this->assertTrue(app(Assinaturas::class)->da($this->tenant)->suspensa());
        $this->actingAs($this->admin)->get('/dashboard')->assertRedirect(route('assinatura.suspensa'));
        $this->actingAs($vendedor)->get('/leads')->assertRedirect(route('assinatura.suspensa'));
        $this->actingAs($this->admin)->getJson('/api/leads')->assertStatus(402)->assertJsonPath('assinatura', 'suspensa');
    }

    public function test_suspensa_ainda_deixa_ver_planos_faturas_exportar_perfil_e_sair(): void
    {
        $this->periodoDeTesteEncerradoHa(31);

        $this->actingAs($this->admin)->get('/configuracoes/assinatura')->assertOk();
        $this->actingAs($this->admin)->post('/configuracoes/assinatura', ['plano' => 'inicial', 'ciclo' => 'mensal'])->assertSessionHas('sucesso');
        $this->actingAs($this->admin)->get('/api/leads/exportar')->assertOk();
        $this->actingAs($this->admin)->get('/profile')->assertOk();
        $this->actingAs($this->admin)->post('/logout')->assertRedirect();
    }

    public function test_pagina_de_acesso_suspenso_explica_e_mostra_o_caminho_certo_para_cada_papel(): void
    {
        $this->periodoDeTesteEncerradoHa(31);
        $vendedor = $this->agente($this->tenant);

        $this->actingAs($this->admin)->get('/assinatura/suspensa')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Assinatura/Suspensa')
            ->where('podeGerenciar', true)
            ->where('assinatura.suspensa', true)
            ->where('assinatura.status', 'teste_expirado'));
        $this->actingAs($vendedor)->get('/assinatura/suspensa')->assertOk()->assertInertia(fn (Assert $page) => $page->where('podeGerenciar', false));
    }

    public function test_pagina_de_acesso_suspenso_volta_ao_dashboard_quando_nao_ha_suspensao(): void
    {
        $this->periodoDeTesteEncerradoHa(5);

        $this->actingAs($this->admin)->get('/assinatura/suspensa')->assertRedirect(route('dashboard'));
        $this->actingAs($this->admin)->get('/dashboard')->assertOk();
    }

    public function test_pagamento_confirmado_tira_a_suspensao(): void
    {
        $this->periodoDeTesteEncerradoHa(40);
        $fatura = app(Faturamento::class)->gerar($this->tenant, 'inicial', 'mensal', 'contratacao');

        app(Faturamento::class)->pagar($fatura);

        $this->actingAs($this->admin)->get('/dashboard')->assertOk();
        $this->gravar()->assertCreated();
    }
}
