<?php

namespace Tests\Feature\Assinaturas;

use App\Models\Assinatura;
use App\Models\Fatura;
use App\Models\Funil;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Assinaturas\Faturamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Usuários são assentos comprados: o plano pago cobre N usuários e, para
 * ter mais, paga-se a diferença. Upgrade (mais caro por mês, ciclo igual
 * ou mais longo) vale no pagamento, com crédito do que sobra do ciclo
 * atual. O resto (plano menor, menos usuários, ciclo mais curto) só na
 * janela de renovação, substituindo a fatura de renovação.
 */
class TrocaDePlanoEAssentosTest extends TestCase
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
        Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
    }

    private function planoPago(string $plano, string $ciclo, int $assentos, string $inicio, string $vigenteAte, ?float $valorDoCiclo = null): Assinatura
    {
        return Assinatura::create([
            'tenant_id' => $this->tenant->id, 'status' => 'ativa', 'plano' => $plano, 'ciclo' => $ciclo, 'assentos' => $assentos,
            'teste_termina_em' => Carbon::parse($inicio)->subDays(14), 'inicio_do_ciclo' => $inicio, 'vigente_ate' => $vigenteAte, 'valor_do_ciclo' => $valorDoCiclo,
        ]);
    }

    private function escolher(string $plano, string $ciclo, ?int $assentos = null)
    {
        return $this->actingAs($this->admin)->post('/configuracoes/assinatura', array_filter(['plano' => $plano, 'ciclo' => $ciclo, 'assentos' => $assentos]));
    }

    private function convidar(string $email)
    {
        return $this->actingAs($this->admin)->postJson('/api/equipe/convites', ['email' => $email, 'papel' => 'vendedor']);
    }

    public function test_upgrade_no_meio_do_ciclo_comeca_no_pagamento_com_credito_do_que_sobra(): void
    {
        // Inicial anual pago (R$ 468), metade do ano usada: 182 de 365 dias restantes.
        $this->planoPago('inicial', 'anual', 1, '2026-04-24 10:00:00', '2027-04-24 10:00:00', 468);

        $this->escolher('profissional', 'anual')->assertSessionHas('sucesso');

        $f = Fatura::where('tenant_id', $this->tenant->id)->sole();
        $credito = round(468 * 182 / 365, 2);
        $this->assertSame(['troca', 1, number_format($credito, 2, '.', ''), number_format(828 - $credito, 2, '.', '')], [$f->motivo, $f->usuarios, $f->credito, $f->valor]);

        app(Faturamento::class)->pagar($f);
        $a = Assinatura::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame(['profissional', 'anual', '2026-10-24', '2027-10-24', 1, '828.00'], [$a->plano, $a->ciclo, $a->inicio_do_ciclo->toDateString(), $a->vigente_ate->toDateString(), $a->assentos, $a->valor_do_ciclo]);
    }

    public function test_plano_menor_ou_ciclo_mais_curto_no_meio_do_ciclo_vale_so_na_renovacao(): void
    {
        $this->planoPago('inicial', 'anual', 1, '2026-04-24 10:00:00', '2027-04-24 10:00:00', 468);

        $this->escolher('profissional', 'mensal')->assertSessionHasErrors(['plano' => 'Trocar para um plano menor, menos usuários ou um ciclo mais curto vale na renovação. A partir de 17/04/2027, escolha o novo plano aqui.']);
        Assinatura::where('tenant_id', $this->tenant->id)->update(['plano' => 'profissional']);
        $this->escolher('inicial', 'anual')->assertSessionHasErrors('plano');
        $this->assertSame(0, Fatura::count());
    }

    public function test_na_janela_de_renovacao_a_escolha_substitui_a_fatura_de_renovacao_e_os_limites_dela_ja_valem(): void
    {
        $this->agente($this->tenant); // 2 usuários
        $this->planoPago('profissional', 'mensal', 2, '2026-09-29 10:00:00', '2026-10-29 10:00:00', 178);
        $this->artisan('assinaturas:rotina')->assertSuccessful(); // renovação do Profissional

        $this->escolher('inicial', 'mensal')->assertSessionHas('sucesso');

        $pendente = Fatura::where('tenant_id', $this->tenant->id)->where('status', 'pendente')->sole();
        $this->assertSame(['renovacao', 'inicial', 2, '2026-10-29'], [$pendente->motivo, $pendente->plano, $pendente->usuarios, $pendente->vence_em->toDateString()]);
        // Enquanto a renovação para o Inicial está pendente, a empresa não passa do Inicial.
        $this->actingAs($this->admin)->postJson('/api/funis', ['nome' => 'Segundo funil'])->assertStatus(422)->assertJsonPath('limite', 'funis');
    }

    public function test_no_teste_a_fatura_pendente_limita_o_que_a_empresa_acrescenta(): void
    {
        $this->escolher('inicial', 'mensal')->assertSessionHas('sucesso'); // 1 assento

        $this->convidar('um@conv.test')->assertStatus(422)->assertJsonPath('limite', 'usuarios')
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'A fatura pendente cobre 1 usuário'));
        $this->actingAs($this->admin)->postJson('/api/funis', ['nome' => 'Segundo funil'])->assertStatus(422);

        $this->escolher('inicial', 'mensal', 2)->assertSessionHas('sucesso');
        $this->convidar('um@conv.test')->assertCreated();
    }

    public function test_assentos_pagos_limitam_os_usuarios_e_desativar_antes_da_renovacao_nao_burla(): void
    {
        $outros = collect(range(1, 4))->map(fn () => $this->agente($this->tenant));
        $this->planoPago('profissional', 'mensal', 5, '2026-10-14 10:00:00', '2026-11-14 10:00:00', 445);
        $outros->each(fn (User $u) => $u->forceFill(['ativo' => false])->save());

        Carbon::setTestNow('2026-11-09 10:00:00');
        $this->artisan('assinaturas:rotina')->assertSuccessful(); // renovação para 1 assento
        $reativar = fn () => $this->actingAs($this->admin)->patchJson("/api/equipe/{$outros[0]->id}/reativar");
        $reativar()->assertStatus(422)->assertJsonPath('limite', 'usuarios');

        app(Faturamento::class)->pagar(Fatura::where('status', 'pendente')->sole());
        $reativar()->assertStatus(422)->assertJsonPath('message', fn (string $m) => str_contains($m, 'Seu plano cobre 1 usuário'));

        // Comprar o assento: mesmo plano, mais usuários = upgrade, vale no pagamento.
        $this->escolher('profissional', 'mensal', 2)->assertSessionHas('sucesso');
        app(Faturamento::class)->pagar(Fatura::where('status', 'pendente')->sole());
        $reativar()->assertOk();
    }

    public function test_assentos_abaixo_do_uso_ou_acima_do_limite_do_plano_sao_recusados(): void
    {
        $this->agente($this->tenant);
        $this->agente($this->tenant); // 3 usuários
        app(\App\Services\Assinaturas\Assinaturas::class)->da($this->tenant);

        $this->escolher('profissional', 'mensal', 2)->assertSessionHasErrors(['assentos' => 'A empresa tem 3 usuários entre ativos e convites pendentes. Escolha pelo menos 3.']);
        $this->escolher('inicial', 'mensal', 4)->assertSessionHasErrors(['assentos' => 'O plano Inicial permite até 3 usuários.']);
        $this->assertSame(0, Fatura::count());
    }

    public function test_cancelar_e_retomar_dentro_da_janela_gera_a_fatura_de_renovacao_de_novo(): void
    {
        $this->planoPago('profissional', 'mensal', 1, '2026-09-29 10:00:00', '2026-10-29 10:00:00', 89);
        $this->artisan('assinaturas:rotina')->assertSuccessful();

        $this->actingAs($this->admin)->post('/configuracoes/assinatura/cancelar-renovacao');
        $this->actingAs($this->admin)->post('/configuracoes/assinatura/retomar-renovacao')
            ->assertSessionHas('sucesso', fn (string $m) => str_contains($m, 'Fatura de R$ 89,00 gerada'));

        $this->assertSame('renovacao', Fatura::where('status', 'pendente')->sole()->motivo);
    }

    public function test_fatura_de_upgrade_nao_paga_nao_impede_a_renovacao(): void
    {
        $this->planoPago('inicial', 'mensal', 1, '2026-09-30 10:00:00', '2026-10-30 10:00:00', 49);
        $this->escolher('empresa', 'anual')->assertSessionHas('sucesso'); // troca, vence em 3 dias

        $this->artisan('assinaturas:rotina')->assertSuccessful();
        $this->assertSame('troca', Fatura::where('status', 'pendente')->sole()->motivo); // ainda pode ser paga

        Carbon::setTestNow('2026-10-28 10:00:00'); // venceu sem pagamento
        $this->artisan('assinaturas:rotina')->assertSuccessful();
        $pendente = Fatura::where('status', 'pendente')->sole();
        $this->assertSame(['renovacao', 'inicial', '2026-10-30'], [$pendente->motivo, $pendente->plano, $pendente->vence_em->toDateString()]);
    }

    public function test_fatura_do_teste_vence_no_fim_do_teste_mesmo_perto_do_fim(): void
    {
        Assinatura::create(['tenant_id' => $this->tenant->id, 'status' => 'teste', 'plano' => 'inicial', 'teste_termina_em' => Carbon::parse('2026-10-25 09:00:00')]);

        $this->escolher('inicial', 'mensal')->assertSessionHas('sucesso');

        $this->assertSame('2026-10-25', Fatura::sole()->vence_em->toDateString());
    }
}
