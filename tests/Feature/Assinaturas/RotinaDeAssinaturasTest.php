<?php

namespace Tests\Feature\Assinaturas;

use App\Mail\AvisoDeAssinaturaMail;
use App\Models\Assinatura;
use App\Models\Fatura;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Assinaturas\Faturamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Rotina diária (assinaturas:rotina): gera a fatura de renovação 7 dias
 * antes e envia cada aviso uma vez só por empresa e prazo, aos admins
 * ativos. Fatura gerada e pagamento confirmado avisam na hora.
 */
class RotinaDeAssinaturasTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['comercial.exigir_assinatura' => true, 'comercial.contato.email' => 'comercial@crm.test']);
        Mail::fake();
        Carbon::setTestNow('2026-10-24 10:00:00');
        $this->tenant = $this->novoTenant();
        $this->admin = $this->agente($this->tenant, 'admin');
    }

    private function plano(array $attrs): Assinatura
    {
        return Assinatura::create($attrs + ['tenant_id' => $this->tenant->id, 'status' => 'ativa', 'plano' => 'profissional', 'ciclo' => 'mensal', 'teste_termina_em' => now()->subMonth()]);
    }

    private function rodar(): void
    {
        $this->artisan('assinaturas:rotina')->assertSuccessful();
    }

    private function enviados(string $tipo): int
    {
        return Mail::queued(AvisoDeAssinaturaMail::class, fn ($m) => $m->tipo === $tipo)->count();
    }

    public function test_gera_a_fatura_de_renovacao_sete_dias_antes_uma_vez_e_avisa_admin_e_comercial(): void
    {
        $this->agente($this->tenant); // 2 usuários
        $this->plano(['vigente_ate' => Carbon::parse('2026-10-31 10:00:00')]);

        $this->rodar();
        $this->rodar();

        $f = Fatura::where('tenant_id', $this->tenant->id)->sole();
        $this->assertSame(['pendente', 'renovacao', 2, '178.00', '2026-10-31'], [$f->status, $f->motivo, $f->usuarios, $f->valor, $f->vence_em->toDateString()]);
        $this->assertSame(1, $this->enviados('fatura_gerada'));
        Mail::assertQueued(AvisoDeAssinaturaMail::class, fn ($m) => $m->tipo === 'fatura_gerada' && $m->hasTo($this->admin->email));
        Mail::assertQueued(AvisoDeAssinaturaMail::class, fn ($m) => $m->tipo === 'comercial_fatura_gerada' && $m->hasTo('comercial@crm.test'));
    }

    public function test_nao_gera_renovacao_antes_da_hora_nem_com_renovacao_cancelada(): void
    {
        $this->plano(['vigente_ate' => now()->addDays(8)]);
        $this->rodar();
        $this->assertSame(0, Fatura::count());

        Assinatura::where('tenant_id', $this->tenant->id)->update(['vigente_ate' => now()->addDays(5), 'renovacao_cancelada_em' => now()]);
        $this->rodar();
        $this->assertSame(0, Fatura::count());
        $this->assertSame(1, $this->enviados('plano_termina'));
    }

    public function test_avisa_o_fim_do_teste_com_7_3_e_1_dia_cada_um_uma_vez(): void
    {
        Assinatura::create(['tenant_id' => $this->tenant->id, 'status' => 'teste', 'plano' => 'inicial', 'teste_termina_em' => Carbon::parse('2026-10-29 10:00:00')]);

        $this->rodar(); // faltam 5
        $this->rodar();
        Carbon::setTestNow('2026-10-27 10:00:00'); // faltam 2
        $this->rodar();
        Carbon::setTestNow('2026-10-28 10:00:00'); // falta 1
        $this->rodar();

        $this->assertSame(3, $this->enviados('teste_acabando'));
        $this->assertSame([5, 2, 1], Mail::queued(AvisoDeAssinaturaMail::class, fn ($m) => $m->tipo === 'teste_acabando')->map(fn ($m) => $m->dados['dias'])->values()->all());
    }

    public function test_lembra_a_fatura_pendente_3_e_1_dia_antes_e_para_quando_paga(): void
    {
        $this->plano(['vigente_ate' => Carbon::parse('2026-10-31 10:00:00')]);
        $this->rodar(); // gera a renovação
        Carbon::setTestNow('2026-10-28 10:00:00');
        $this->rodar();
        $this->assertSame(1, $this->enviados('fatura_vencendo'));

        app(Faturamento::class)->pagar(Fatura::sole());
        Carbon::setTestNow('2026-10-30 10:00:00');
        $this->rodar();
        $this->assertSame(1, $this->enviados('fatura_vencendo'));
        $this->assertSame(1, $this->enviados('pagamento_confirmado'));
    }

    public function test_da_os_avisos_de_atraso_limitacao_e_suspensao_na_ordem_e_uma_vez(): void
    {
        $this->plano(['vigente_ate' => Carbon::parse('2026-10-23 10:00:00')]); // carência até 26/10

        $this->rodar();
        $this->rodar();
        $this->assertSame(1, $this->enviados('pagamento_atrasado'));

        Carbon::setTestNow('2026-10-27 10:00:00'); // só leitura desde 26/10, suspende em 25/11
        $this->rodar();
        Carbon::setTestNow('2026-11-19 10:00:00');
        $this->rodar();
        Carbon::setTestNow('2026-11-26 10:00:00');
        $this->rodar();
        $this->rodar();

        $this->assertSame([1, 1, 1], [$this->enviados('acesso_limitado'), $this->enviados('suspensao_proxima'), $this->enviados('suspensa')]);
    }

    public function test_teste_encerrado_avisa_uma_vez(): void
    {
        Assinatura::create(['tenant_id' => $this->tenant->id, 'status' => 'teste', 'plano' => 'inicial', 'teste_termina_em' => now()->subHours(3)]);

        $this->rodar();
        $this->rodar();

        $this->assertSame(1, $this->enviados('teste_encerrado'));
    }

    public function test_so_admins_ativos_da_propria_empresa_recebem(): void
    {
        $vendedor = $this->agente($this->tenant);
        $inativo = $this->agente($this->tenant, 'admin');
        $inativo->forceFill(['ativo' => false])->save();
        $outra = $this->novoTenant();
        $adminDeOutra = $this->agente($outra, 'admin');
        $this->ativar($this->tenant);
        Assinatura::create(['tenant_id' => $this->tenant->id, 'status' => 'teste', 'plano' => 'inicial', 'teste_termina_em' => now()->subHour()]);

        $this->rodar();

        Mail::assertQueued(AvisoDeAssinaturaMail::class, fn ($m) => $m->tipo === 'teste_encerrado'
            && $m->hasTo($this->admin->email) && ! $m->hasTo($vendedor->email) && ! $m->hasTo($inativo->email) && ! $m->hasTo($adminDeOutra->email));
    }

    public function test_contratar_pela_tela_avisa_o_admin_e_a_equipe_comercial(): void
    {
        Assinatura::create(['tenant_id' => $this->tenant->id, 'status' => 'teste', 'plano' => 'inicial', 'teste_termina_em' => now()->addDays(10)]);

        $this->actingAs($this->admin)->post('/configuracoes/assinatura', ['plano' => 'profissional', 'ciclo' => 'mensal']);

        Mail::assertQueued(AvisoDeAssinaturaMail::class, fn ($m) => $m->tipo === 'fatura_gerada' && $m->hasTo($this->admin->email));
        Mail::assertQueued(AvisoDeAssinaturaMail::class, fn ($m) => $m->tipo === 'comercial_fatura_gerada' && $m->hasTo('comercial@crm.test')
            && str_contains($m->render(), 'php artisan fatura:pagar'));
    }

    public function test_email_da_fatura_traz_valor_vencimento_e_instrucoes_de_pagamento(): void
    {
        config(['comercial.pagamento.instrucoes' => 'Pix: financeiro@crm.test']);
        Assinatura::create(['tenant_id' => $this->tenant->id, 'status' => 'teste', 'plano' => 'inicial', 'teste_termina_em' => now()->addDays(10)]);

        app(Faturamento::class)->gerar($this->tenant, 'profissional', 'mensal', 'contratacao');

        Mail::assertQueued(AvisoDeAssinaturaMail::class, function ($m) {
            $html = $m->render();

            return $m->tipo === 'fatura_gerada' && str_contains($html, 'R$ 89,00') && str_contains($html, '03/11/2026') && str_contains($html, 'Pix: financeiro@crm.test');
        });
    }

    public function test_ativacao_manual_pelo_comando_nao_manda_fatura(): void
    {
        $this->artisan('assinatura:ativar', ['tenant' => $this->tenant->id, 'plano' => 'inicial'])->assertSuccessful();

        $this->assertSame(0, $this->enviados('fatura_gerada'));
        $this->assertSame(1, $this->enviados('pagamento_confirmado'));
    }

    public function test_rotina_esta_agendada_todo_dia(): void
    {
        Artisan::call('schedule:list');
        $this->assertStringContainsString('assinaturas:rotina', Artisan::output());
    }

    public function test_sem_exigir_assinatura_a_rotina_nao_cobra_nem_avisa(): void
    {
        config(['comercial.exigir_assinatura' => false]);
        $this->plano(['vigente_ate' => now()->addDays(3)]);

        $this->rodar();

        $this->assertSame(0, Fatura::count());
        Mail::assertNothingQueued();
    }
}
