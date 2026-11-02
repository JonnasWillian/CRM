<?php

namespace Tests\Feature\Onboarding;

use App\Models\PerfilDeEmpresa;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Assinaturas\Assinaturas;
use App\Services\TenantBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * O cargo respondido no questionário define em que visão o Dashboard abre
 * (equipe ou meus leads), e a escolha no filtro passa a ser lembrada. O
 * tamanho da equipe sugere o número de usuários na Assinatura.
 */
class VisaoDoDashboardTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    /** @return array{0: Tenant, 1: User, 2: User} */
    private function cenario(?string $visao): array
    {
        $t = $this->novoTenant();
        TenantBootstrapper::bootstrap($t);
        $admin = $this->agente($t, 'admin');
        $admin->forceFill(['dashboard_visao' => $visao])->save();
        $vendedor = $this->agente($t, 'vendedor');

        return [$t, $admin, $vendedor];
    }

    private function userIdNoDashboard(User $u, array $query = []): mixed
    {
        $valor = 'nao-lido';
        $this->actingAs($u)->get(route('dashboard', $query))->assertOk()
            ->assertInertia(function (Assert $p) use (&$valor) {
                $valor = $p->toArray()['props']['periodo']['user_id'];
            });

        return $valor;
    }

    public function test_preferencia_meus_abre_o_dashboard_nos_proprios_leads(): void
    {
        [, $admin] = $this->cenario('meus');

        $this->assertSame($admin->id, $this->userIdNoDashboard($admin));
    }

    public function test_sem_preferencia_abre_na_equipe(): void
    {
        [, $admin] = $this->cenario(null);

        $this->assertNull($this->userIdNoDashboard($admin));
    }

    public function test_escolher_toda_equipe_grava_a_preferencia(): void
    {
        [, $admin] = $this->cenario('meus');

        $this->assertNull($this->userIdNoDashboard($admin, ['visao' => 'equipe']));
        $this->assertSame('equipe', $admin->fresh()->dashboard_visao);
        $this->assertNull($this->userIdNoDashboard($admin->fresh()));
    }

    public function test_escolher_a_si_mesmo_grava_meus(): void
    {
        [, $admin] = $this->cenario('equipe');

        $this->assertSame($admin->id, $this->userIdNoDashboard($admin, ['user_id' => $admin->id]));
        $this->assertSame('meus', $admin->fresh()->dashboard_visao);
    }

    public function test_outro_vendedor_nao_muda_a_preferencia(): void
    {
        [, $admin, $vendedor] = $this->cenario('meus');

        $this->assertSame($vendedor->id, $this->userIdNoDashboard($admin, ['user_id' => $vendedor->id]));
        $this->assertSame('meus', $admin->fresh()->dashboard_visao);
    }

    public function test_assinatura_sugere_usuarios_da_resposta(): void
    {
        [$t, $admin] = $this->cenario(null);
        app(Assinaturas::class)->da($t);
        $perfil = PerfilDeEmpresa::create(['tenant_id' => $t->id]);
        $perfil->forceFill(['respondido_em' => now(), 'respostas' => ['tamanho' => '6-10']])->save();

        $this->actingAs($admin)->get(route('configuracoes.assinatura'))->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('usuariosSugeridos', 6));
    }
}
