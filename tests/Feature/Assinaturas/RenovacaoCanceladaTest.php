<?php

namespace Tests\Feature\Assinaturas;

use App\Models\Assinatura;
use App\Models\Fatura;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Assinaturas\Faturamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Cancelar é cancelar a renovação: usa até o fim do ciclo pago, não recebe
 * mais fatura, e depois fica só leitura (sem carência). Dá para desfazer
 * enquanto o ciclo não acabou.
 */
class RenovacaoCanceladaTest extends TestCase
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
        Assinatura::create(['tenant_id' => $this->tenant->id, 'status' => 'ativa', 'plano' => 'profissional', 'ciclo' => 'mensal', 'teste_termina_em' => now()->subMonth(), 'vigente_ate' => now()->addDays(5)]);
    }

    private function gravar()
    {
        $lead = $this->lead($this->tenant, $this->admin);

        return $this->actingAs($this->admin)->postJson("/api/leads/{$lead->id}/tarefas", ['titulo' => 'x', 'data_limite' => today()->toDateString()]);
    }

    public function test_cancelar_mantem_o_ciclo_pago_cancela_a_fatura_e_depois_bloqueia_sem_carencia(): void
    {
        $fatura = app(Faturamento::class)->gerar($this->tenant, 'profissional', 'mensal', 'renovacao');

        $this->actingAs($this->admin)->post('/configuracoes/assinatura/cancelar-renovacao')
            ->assertRedirect('/configuracoes/assinatura')
            ->assertSessionHas('sucesso', 'Renovação cancelada. O plano Profissional continua ativo até 29/10/2026.');

        $this->assertSame('cancelada', $fatura->fresh()->status);
        $this->gravar()->assertCreated();
        $this->actingAs($this->admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('assinatura.renovacao_cancelada', true)->where('assinatura.status', 'ativa'));

        Carbon::setTestNow('2026-10-30 10:00:00');
        $this->assertSame('cancelada', Assinatura::where('tenant_id', $this->tenant->id)->first()->situacao());
        $this->gravar()->assertStatus(402);
        $this->actingAs($this->admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('assinatura.somente_leitura_desde', '2026-10-29'));
    }

    public function test_retomar_desfaz_o_cancelamento(): void
    {
        $this->actingAs($this->admin)->post('/configuracoes/assinatura/cancelar-renovacao');
        $this->actingAs($this->admin)->post('/configuracoes/assinatura/retomar-renovacao')
            ->assertRedirect('/configuracoes/assinatura')->assertSessionHas('sucesso');

        Carbon::setTestNow('2026-10-30 10:00:00');
        $this->assertSame('carencia', Assinatura::where('tenant_id', $this->tenant->id)->first()->situacao());
    }

    public function test_so_cancela_plano_pago_em_vigor(): void
    {
        Assinatura::where('tenant_id', $this->tenant->id)->update(['status' => 'teste', 'teste_termina_em' => now()->addDays(3), 'vigente_ate' => null]);

        $this->actingAs($this->admin)->post('/configuracoes/assinatura/cancelar-renovacao')->assertSessionHas('erro');
        $this->assertNull(Assinatura::where('tenant_id', $this->tenant->id)->value('renovacao_cancelada_em'));
    }

    public function test_so_quem_gerencia_a_empresa_cancela(): void
    {
        $vendedor = $this->agente($this->tenant);

        $this->actingAs($vendedor)->post('/configuracoes/assinatura/cancelar-renovacao')->assertForbidden();
        $this->actingAs($vendedor)->post('/configuracoes/assinatura/retomar-renovacao')->assertForbidden();
    }

    public function test_pagar_uma_nova_fatura_desfaz_o_cancelamento(): void
    {
        $this->actingAs($this->admin)->post('/configuracoes/assinatura/cancelar-renovacao');
        $fatura = app(Faturamento::class)->gerar($this->tenant, 'profissional', 'anual', 'contratacao');

        app(Faturamento::class)->pagar($fatura);

        $this->assertNull(Assinatura::where('tenant_id', $this->tenant->id)->value('renovacao_cancelada_em'));
        $this->assertSame(1, Fatura::where('tenant_id', $this->tenant->id)->where('status', 'paga')->count());
    }
}
