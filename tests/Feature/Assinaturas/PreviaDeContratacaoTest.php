<?php

namespace Tests\Feature\Assinaturas;

use App\Models\Assinatura;
use App\Models\Fatura;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * GET configuracoes/assinatura/previa: a tela confirma a contratação com os
 * números do servidor (tipo, usuários, bruto, crédito, valor, vencimento),
 * sem gravar nada. Recusas chegam como 422 com o campo culpado.
 */
class PreviaDeContratacaoTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['comercial.exigir_assinatura' => true]);
        Mail::fake();
        Carbon::setTestNow('2026-10-29 10:00:00');
        $this->tenant = $this->novoTenant();
        $this->admin = $this->agente($this->tenant, 'admin');
    }

    private function previa(array $params, ?User $quem = null)
    {
        return $this->actingAs($quem ?? $this->admin)->getJson('/configuracoes/assinatura/previa?'.http_build_query($params));
    }

    public function test_no_teste_mostra_a_contratacao_para_os_usuarios_de_hoje_sem_gravar_fatura(): void
    {
        $this->agente($this->tenant); // 2 usuários
        Assinatura::create(['tenant_id' => $this->tenant->id, 'status' => 'teste', 'plano' => 'inicial', 'teste_termina_em' => Carbon::parse('2026-11-05 10:00:00')]);

        $this->previa(['plano' => 'profissional', 'ciclo' => 'mensal'])->assertOk()->assertExactJson([
            'motivo' => 'contratacao', 'plano' => 'profissional', 'ciclo' => 'mensal', 'usuarios' => 2,
            'bruto' => 178, 'credito' => 0, 'valor' => 178, 'vence_em' => '2026-11-05',
        ]);
        $this->assertSame(0, Fatura::count());
    }

    public function test_respeita_os_usuarios_escolhidos_e_o_minimo_do_plano(): void
    {
        $this->previa(['plano' => 'profissional', 'ciclo' => 'anual', 'assentos' => 4])->assertOk()
            ->assertJsonPath('usuarios', 4)->assertJsonPath('bruto', 3312);
        $this->previa(['plano' => 'empresa', 'ciclo' => 'mensal', 'assentos' => 2])->assertOk()
            ->assertJsonPath('usuarios', 5)->assertJsonPath('bruto', 695);
    }

    public function test_upgrade_no_meio_do_ciclo_mostra_o_credito_e_o_valor_final(): void
    {
        Assinatura::create([
            'tenant_id' => $this->tenant->id, 'status' => 'ativa', 'plano' => 'inicial', 'ciclo' => 'anual', 'assentos' => 1,
            'teste_termina_em' => now()->subYear(), 'inicio_do_ciclo' => '2026-04-29 10:00:00', 'vigente_ate' => '2027-04-29 10:00:00', 'valor_do_ciclo' => 468,
        ]);

        $r = $this->previa(['plano' => 'profissional', 'ciclo' => 'anual'])->assertOk()->assertJsonPath('motivo', 'troca');

        $this->assertGreaterThan(0, $r->json('credito'));
        $this->assertEqualsWithDelta(828 - $r->json('credito'), $r->json('valor'), 0.001);
        $this->assertSame('2026-11-01', $r->json('vence_em'));
    }

    public function test_recusas_chegam_como_422_com_o_campo_culpado(): void
    {
        $this->agente($this->tenant);
        $this->agente($this->tenant); // 3 usuários
        Assinatura::create(['tenant_id' => $this->tenant->id, 'status' => 'ativa', 'plano' => 'profissional', 'ciclo' => 'anual', 'assentos' => 3,
            'teste_termina_em' => now()->subYear(), 'inicio_do_ciclo' => now()->subMonth(), 'vigente_ate' => now()->addMonths(11), 'valor_do_ciclo' => 2484]);

        $this->previa(['plano' => 'profissional', 'ciclo' => 'anual', 'assentos' => 2])->assertStatus(422)->assertJsonValidationErrors('assentos');
        $this->previa(['plano' => 'profissional', 'ciclo' => 'mensal'])->assertStatus(422)->assertJsonValidationErrors('plano');
        $this->previa(['plano' => 'ouro', 'ciclo' => 'mensal'])->assertStatus(422)->assertJsonValidationErrors('plano');
        $this->previa(['plano' => 'inicial', 'ciclo' => 'semanal'])->assertStatus(422)->assertJsonValidationErrors('ciclo');
    }

    public function test_so_quem_gerencia_a_empresa_ve_a_previa(): void
    {
        $this->previa(['plano' => 'profissional', 'ciclo' => 'mensal'], $this->agente($this->tenant))->assertForbidden();
    }

    public function test_empresa_suspensa_ainda_consegue_ver_a_previa_para_regularizar(): void
    {
        Assinatura::create(['tenant_id' => $this->tenant->id, 'status' => 'teste', 'plano' => 'inicial', 'teste_termina_em' => now()->subDays(40)]);

        $this->previa(['plano' => 'inicial', 'ciclo' => 'mensal'])->assertOk()->assertJsonPath('motivo', 'contratacao');
    }
}
