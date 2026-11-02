<?php

namespace Tests\Feature\Onboarding;

use App\Models\PerfilDeEmpresa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/** Base do primeiro acesso: estado do passo a passo no usuário, perfil da empresa e o config. */
class EsquemaDoOnboardingTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_usuario_novo_nasce_com_tour_pendente_completo(): void
    {
        $u = $this->agente($this->novoTenant())->fresh();

        $this->assertSame('pendente', $u->tour_estado);
        $this->assertSame('completa', $u->tour_versao);
        $this->assertNull($u->tour_passo);
    }

    public function test_perfil_resolvido_quando_respondido_ou_pulado(): void
    {
        $t = $this->novoTenant();
        $perfil = PerfilDeEmpresa::create(['tenant_id' => $t->id]);

        $this->assertFalse($perfil->resolvido());
        $this->assertTrue(PerfilDeEmpresa::pendente($t->id));
        $this->assertSame($perfil->id, PerfilDeEmpresa::daEmpresa($t->id)?->id);

        $perfil->forceFill(['pulado_em' => Carbon::now()])->save();
        $this->assertTrue($perfil->fresh()->resolvido());
        $this->assertFalse(PerfilDeEmpresa::pendente($t->id));

        $outra = $this->novoTenant();
        $this->assertFalse(PerfilDeEmpresa::pendente($outra->id)); // sem linha: nunca pendente
        $respondido = PerfilDeEmpresa::create(['tenant_id' => $outra->id]);
        $respondido->forceFill(['respondido_em' => Carbon::now()])->save();
        $this->assertTrue($respondido->fresh()->resolvido());
    }

    public function test_config_cobre_todos_os_ramos_da_spec(): void
    {
        $ramos = config('onboarding.ramos');

        $this->assertSame(
            ['agencia', 'consultoria', 'software', 'servicos', 'educacao', 'saude', 'imobiliario', 'industria', 'varejo', 'eventos', 'outro'],
            array_keys($ramos),
        );
        foreach ($ramos as $chave => $ramo) {
            if ($chave === 'outro') {
                $this->assertNull($ramo['estagios']);

                continue;
            }
            $this->assertGreaterThanOrEqual(3, count($ramo['estagios']), $chave);
            $this->assertLessThanOrEqual(4, count($ramo['estagios']), $chave);
            $this->assertNotEmpty($ramo['ganho'], $chave);
            $this->assertNotEmpty($ramo['perdido'], $chave);
            $this->assertLessThanOrEqual(2, count($ramo['motivos']), $chave);
        }
        $this->assertSame(51, config('onboarding.tamanhos.51+.minimo'));
        $this->assertSame('Nome da empresa', config('onboarding.para_quem.empresas.nome_do_lead'));
    }
}
