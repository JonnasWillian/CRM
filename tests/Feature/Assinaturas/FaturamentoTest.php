<?php

namespace Tests\Feature\Assinaturas;

use App\Models\Assinatura;
use App\Models\Fatura;
use App\Services\Assinaturas\Assinaturas;
use App\Services\Assinaturas\Faturamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Contratar gera fatura; só o pagamento confirmado ativa. Valor por usuário
 * ativo: mensal = preço × usuários; anual = preço anual × 12 × usuários.
 */
class FaturamentoTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['comercial.exigir_assinatura' => true]);
        Mail::fake();
        Carbon::setTestNow('2026-10-24 10:00:00');
    }

    private function empresaComUsuarios(int $usuarios): array
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        foreach (range(2, max(2, $usuarios)) as $i) {
            if ($i <= $usuarios) {
                $this->agente($tenant);
            }
        }
        app(Assinaturas::class)->da($tenant); // teste até 07/11

        return [$tenant, $admin];
    }

    public function test_escolher_plano_gera_fatura_por_usuario_e_nao_ativa(): void
    {
        [$tenant, $admin] = $this->empresaComUsuarios(4);

        $this->actingAs($admin)->post('/configuracoes/assinatura', ['plano' => 'profissional', 'ciclo' => 'mensal'])
            ->assertRedirect('/configuracoes/assinatura')
            ->assertSessionHas('sucesso', 'Fatura de R$ 356,00 gerada. O plano Profissional é ativado assim que o pagamento for confirmado.');

        $f = Fatura::where('tenant_id', $tenant->id)->sole();
        $this->assertSame(['pendente', 'profissional', 'mensal', 4, 1, '89.00', '356.00', 'contratacao'], [$f->status, $f->plano, $f->ciclo, $f->usuarios, $f->meses, $f->preco_unitario, $f->valor, $f->motivo]);
        // Durante o teste, vence no fim do teste.
        $this->assertSame('2026-11-07', $f->vence_em->toDateString());
        $this->assertSame('teste', Assinatura::where('tenant_id', $tenant->id)->value('status'));
    }

    public function test_ciclo_anual_cobra_doze_meses_pelo_preco_anual(): void
    {
        [$tenant] = $this->empresaComUsuarios(4);

        $f = app(Faturamento::class)->gerar($tenant, 'profissional', 'anual', 'contratacao');
        $this->assertSame(['3312.00', 12, '69.00'], [$f->valor, $f->meses, $f->preco_unitario]);
    }

    public function test_plano_com_minimo_de_usuarios_cobra_o_minimo(): void
    {
        [$tenant, $admin] = $this->empresaComUsuarios(2);

        $f = app(Faturamento::class)->gerar($tenant, 'empresa', 'mensal', 'contratacao');
        $this->assertSame([5, '695.00'], [$f->usuarios, $f->valor]);

        $this->actingAs($admin)->get('/configuracoes/assinatura')->assertInertia(fn (Assert $page) => $page
            ->where('planos.2.usuarios', 5)
            ->where('planos.1.usuarios', 2));
    }

    public function test_escolher_outro_plano_cancela_a_fatura_pendente_anterior(): void
    {
        [$tenant, $admin] = $this->empresaComUsuarios(2);

        $this->actingAs($admin)->post('/configuracoes/assinatura', ['plano' => 'profissional', 'ciclo' => 'mensal']);
        $this->actingAs($admin)->post('/configuracoes/assinatura', ['plano' => 'empresa', 'ciclo' => 'anual']);

        $this->assertSame(['cancelada', 'pendente'], Fatura::where('tenant_id', $tenant->id)->orderBy('id')->pluck('status')->all());
    }

    public function test_empresa_bloqueada_so_volta_a_gravar_depois_do_pagamento_confirmado(): void
    {
        [$tenant, $admin] = $this->empresaComUsuarios(1);
        $lead = $this->lead($tenant, $admin);
        Assinatura::where('tenant_id', $tenant->id)->update(['teste_termina_em' => now()->subDay()]);
        $tarefa = fn () => $this->actingAs($admin)->postJson("/api/leads/{$lead->id}/tarefas", ['titulo' => 'x', 'data_limite' => today()->toDateString()]);

        $this->actingAs($admin)->post('/configuracoes/assinatura', ['plano' => 'inicial', 'ciclo' => 'mensal'])->assertSessionHas('sucesso');
        $tarefa()->assertStatus(402);
        $fatura = Fatura::where('tenant_id', $tenant->id)->sole();
        $this->assertSame('2026-10-27', $fatura->vence_em->toDateString()); // fora do teste: 3 dias

        $this->artisan('fatura:pagar', ['fatura' => $fatura->id, '--referencia' => 'pix 24/10'])->assertSuccessful();

        $tarefa()->assertCreated();
        $fatura->refresh();
        $this->assertSame(['paga', 'pix 24/10', 'manual'], [$fatura->status, $fatura->referencia_gateway, $fatura->gateway]);
        $a = Assinatura::where('tenant_id', $tenant->id)->first();
        $this->assertSame(['ativa', 'inicial', 'mensal', '2026-11-24'], [$a->status, $a->plano, $a->ciclo, $a->vigente_ate->toDateString()]);
    }

    public function test_confirmar_pagamento_duas_vezes_nao_ativa_duas_vezes(): void
    {
        [$tenant] = $this->empresaComUsuarios(1);
        Assinatura::where('tenant_id', $tenant->id)->update(['teste_termina_em' => now()->subDay()]);
        $fatura = app(Faturamento::class)->gerar($tenant, 'inicial', 'mensal', 'contratacao');

        app(Faturamento::class)->pagar($fatura, 'ref-1');
        $vigencia = Assinatura::where('tenant_id', $tenant->id)->value('vigente_ate');
        app(Faturamento::class)->pagar($fatura->fresh(), 'ref-1');
        $this->artisan('fatura:pagar', ['fatura' => $fatura->id])->expectsOutputToContain('já estava paga')->assertFailed();

        $this->assertEquals($vigencia, Assinatura::where('tenant_id', $tenant->id)->value('vigente_ate'));
    }

    public function test_fatura_cancelada_nao_pode_ser_paga(): void
    {
        [$tenant] = $this->empresaComUsuarios(1);
        $velha = app(Faturamento::class)->gerar($tenant, 'inicial', 'mensal', 'contratacao');
        app(Faturamento::class)->gerar($tenant, 'profissional', 'mensal', 'contratacao');

        $this->artisan('fatura:pagar', ['fatura' => $velha->id])->expectsOutputToContain('cancelada')->assertFailed();
        $this->assertSame('teste', Assinatura::where('tenant_id', $tenant->id)->value('status'));
    }

    public function test_pagar_durante_o_teste_comeca_a_contar_no_fim_do_teste(): void
    {
        [$tenant] = $this->empresaComUsuarios(1);
        $fatura = app(Faturamento::class)->gerar($tenant, 'inicial', 'mensal', 'contratacao');

        app(Faturamento::class)->pagar($fatura);

        $this->assertSame('2026-12-07', Assinatura::where('tenant_id', $tenant->id)->first()->vigente_ate->toDateString());
    }

    public function test_comando_lista_as_faturas_pendentes(): void
    {
        [$tenant] = $this->empresaComUsuarios(4);
        $tenant->forceFill(['nome' => 'Agência Prisma'])->save();
        $f = app(Faturamento::class)->gerar($tenant, 'profissional', 'mensal', 'contratacao');

        \Illuminate\Support\Facades\Artisan::call('faturas:pendentes');
        $saida = \Illuminate\Support\Facades\Artisan::output();
        $this->assertStringContainsString('Agência Prisma', $saida);
        $this->assertStringContainsString('R$ 356,00', $saida);
        $this->assertStringContainsString('fatura:pagar', $saida);
        app(Faturamento::class)->pagar($f);
        $this->artisan('faturas:pendentes')->expectsOutputToContain('Nenhuma fatura pendente')->assertSuccessful();
    }

    public function test_pagina_mostra_fatura_pendente_historico_valores_e_como_pagar(): void
    {
        config(['comercial.pagamento.instrucoes' => 'Pix: financeiro@exemplo.com.br']);
        [$tenant, $admin] = $this->empresaComUsuarios(3);
        app(Faturamento::class)->gerar($tenant, 'profissional', 'mensal', 'contratacao');

        $this->actingAs($admin)->get('/configuracoes/assinatura')->assertInertia(fn (Assert $page) => $page
            ->where('faturaPendente.valor', 267)
            ->where('faturaPendente.plano_nome', 'Profissional')
            ->where('faturaPendente.vence_em', '2026-11-07')
            ->has('faturas', 1)
            ->where('planos.1.valor.mensal', 267)
            ->where('planos.1.valor.anual', 2484)
            ->where('usuariosCobrados', 3)
            ->where('pagamento.instrucoes', 'Pix: financeiro@exemplo.com.br'));
        $this->actingAs($admin)->get('/dashboard')->assertInertia(fn (Assert $page) => $page->where('assinatura.fatura_pendente.valor', 267));
    }
}
