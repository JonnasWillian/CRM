<?php

namespace Tests\Feature\Onboarding;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\MotivoPerda;
use App\Models\PerfilDeEmpresa;
use App\Models\TarefaPadrao;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Onboarding\PersonalizarEmpresa;
use App\Services\Onboarding\PreferenciasDaEmpresa;
use App\Services\TenantBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/** O questionário monta a empresa: funil do ramo, motivos, modelos por canal, preferências. */
class PersonalizarEmpresaTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    /** @return array{0: Tenant, 1: User} */
    private function cenario(): array
    {
        $t = $this->novoTenant();
        TenantBootstrapper::bootstrap($t);
        $admin = $this->agente($t, 'admin');
        PerfilDeEmpresa::create(['tenant_id' => $t->id]);

        return [$t, $admin];
    }

    private function respostas(array $extra = []): array
    {
        return array_merge([
            'experiencia' => 'nunca', 'cargo' => 'dono', 'ramo' => 'software', 'tamanho' => '6-10',
            'para_quem' => 'empresas', 'canais' => ['whatsapp'], 'origens' => ['indicacao', 'site'],
            'conheceu' => 'google', 'telefone' => '+5511999998888', 'dicas_whatsapp' => true,
        ], $extra);
    }

    private function estagios(): array
    {
        $funil = Funil::where('is_default', true)->firstOrFail();

        return Estagio::where('funil_id', $funil->id)->orderBy('ordem')->get(['descricao', 'tipo', 'cor'])->toArray();
    }

    private function aplicar(Tenant $t, User $u, array $extra = []): PerfilDeEmpresa
    {
        return app(PersonalizarEmpresa::class)->aplicar($t, $u, $this->respostas($extra));
    }

    public function test_ramo_software_troca_os_estagios_do_funil_padrao(): void
    {
        [$t, $admin] = $this->cenario();

        $this->aplicar($t, $admin);

        $estagios = $this->estagios();
        $this->assertSame(['Lead', 'Demonstração', 'Período de teste', 'Proposta', 'Ganho', 'Perdido'], array_column($estagios, 'descricao'));
        $this->assertSame(['aberto', 'aberto', 'aberto', 'aberto', 'ganho', 'perdido'], array_column($estagios, 'tipo'));
        $this->assertSame('#1a7544', $estagios[4]['cor']);
        $this->assertSame('#b42318', $estagios[5]['cor']);
    }

    public function test_com_lead_no_funil_os_estagios_nao_mudam(): void
    {
        [$t, $admin] = $this->cenario();
        $antes = array_column($this->estagios(), 'descricao');
        $this->lead($t, $admin);

        $this->aplicar($t, $admin);

        $this->assertSame($antes, array_column($this->estagios(), 'descricao'));
    }

    public function test_ramo_outro_mantem_o_funil_padrao(): void
    {
        [$t, $admin] = $this->cenario();
        $antes = $this->estagios();

        $this->aplicar($t, $admin, ['ramo' => 'outro']);

        $this->assertSame($antes, $this->estagios());
    }

    public function test_motivos_do_ramo_entram_sem_duplicar(): void
    {
        [$t, $admin] = $this->cenario();
        $padrao = MotivoPerda::count();

        $this->aplicar($t, $admin);
        app(PersonalizarEmpresa::class)->aplicar($t, $admin, $this->respostas()); // já resolvido: não reaplica
        PerfilDeEmpresa::daEmpresa($t->id)->forceFill(['respondido_em' => null])->save();
        $this->aplicar($t, $admin); // reaplica de verdade: não duplica

        $this->assertSame($padrao + 2, MotivoPerda::count());
        $this->assertSame(1, MotivoPerda::where('descricao', 'Faltou funcionalidade')->count());
        $this->assertSame(1, MotivoPerda::where('descricao', 'Ficou com a ferramenta atual')->count());
    }

    public function test_modelos_de_tarefa_seguem_os_canais_no_maximo_quatro(): void
    {
        [$t, $admin] = $this->cenario();

        $this->aplicar($t, $admin, ['canais' => ['whatsapp', 'ligacao', 'email', 'reuniao_online', 'reuniao_presencial', 'visita']]);

        $modelos = TarefaPadrao::where('user_id', $admin->id)->orderBy('id')->get(['titulo', 'prazo_dias'])->toArray();
        $this->assertSame([
            ['titulo' => 'Mandar WhatsApp de acompanhamento', 'prazo_dias' => 2],
            ['titulo' => 'Ligar para retomar o contato', 'prazo_dias' => 3],
            ['titulo' => 'Enviar e-mail com a proposta', 'prazo_dias' => 1],
            ['titulo' => 'Confirmar a reunião', 'prazo_dias' => 1],
        ], $modelos);
    }

    public function test_vendedor_fica_com_dashboard_meus_e_tour_conforme_experiencia(): void
    {
        [$t, $admin] = $this->cenario();
        $this->aplicar($t, $admin, ['cargo' => 'vendedor', 'experiencia' => 'varios']);
        $this->assertSame('meus', $admin->fresh()->dashboard_visao);
        $this->assertSame('curta', $admin->fresh()->tour_versao);

        [$t2, $dono] = $this->cenario();
        $this->aplicar($t2, $dono, ['cargo' => 'dono', 'experiencia' => 'nunca']);
        $this->assertSame('equipe', $dono->fresh()->dashboard_visao);
        $this->assertSame('completa', $dono->fresh()->tour_versao);
    }

    public function test_perfil_grava_respostas_telefone_e_aceite(): void
    {
        [$t, $admin] = $this->cenario();

        $perfil = $this->aplicar($t, $admin)->fresh();

        $this->assertNotNull($perfil->respondido_em);
        $this->assertSame($admin->id, $perfil->respondido_por);
        $this->assertSame('+5511999998888', $perfil->telefone);
        $this->assertTrue($perfil->aceita_dicas_whatsapp);
        $this->assertNotNull($perfil->aceite_dicas_em);
        $this->assertSame('software', $perfil->respostas['ramo']);
    }

    public function test_preferencias_da_empresa(): void
    {
        [$t, $admin] = $this->cenario();
        $this->assertSame(array_values(config('onboarding.origens')), PreferenciasDaEmpresa::origens($t->id));
        $this->assertNull(PreferenciasDaEmpresa::usuariosSugeridos($t->id));
        $this->assertSame('Nome', PreferenciasDaEmpresa::rotuloDoNome($t->id));

        $this->aplicar($t, $admin);

        $this->assertSame('Nome da empresa', PreferenciasDaEmpresa::rotuloDoNome($t->id));
        $this->assertSame(['Indicação', 'Site', 'Outro'], PreferenciasDaEmpresa::origens($t->id));
        $this->assertSame(6, PreferenciasDaEmpresa::usuariosSugeridos($t->id));
        $this->assertTrue(PreferenciasDaEmpresa::equipeComMaisDeUm($t->id));

        PerfilDeEmpresa::daEmpresa($t->id)->forceFill(['respostas' => $this->respostas(['tamanho' => '51+'])])->save();
        $this->assertSame(51, PreferenciasDaEmpresa::usuariosSugeridos($t->id));
        $this->assertSame('curta', PreferenciasDaEmpresa::versaoDoTour(['experiencia' => 'alguns']));
        $this->assertSame('completa', PreferenciasDaEmpresa::versaoDoTour(['experiencia' => 'nunca']));
    }

    public function test_sem_resposta_equipe_conta_ativos_e_convites(): void
    {
        $t = $this->novoTenant();
        $this->agente($t, 'admin');
        $this->assertFalse(PreferenciasDaEmpresa::equipeComMaisDeUm($t->id));

        $this->agente($t, 'vendedor');
        $this->assertTrue(PreferenciasDaEmpresa::equipeComMaisDeUm($t->id));
    }

    public function test_pular_marca_resolvido_sem_mudar_nada(): void
    {
        [$t, $admin] = $this->cenario();
        $antes = $this->estagios();

        $perfil = app(PersonalizarEmpresa::class)->pular($t, $admin);

        $this->assertNotNull($perfil->fresh()->pulado_em);
        $this->assertTrue($perfil->fresh()->resolvido());
        $this->assertSame($antes, $this->estagios());
        $this->assertSame(0, TarefaPadrao::count());
    }
}
