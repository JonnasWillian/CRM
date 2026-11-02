<?php

namespace Tests\Feature\Onboarding;

use App\Mail\AvisoDeAssinaturaMail;
use App\Models\PerfilDeEmpresa;
use App\Models\TarefaPadrao;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Questionário de primeiro acesso: o cadastro leva a ele, o admin de empresa
 * pendente é levado a ele até responder ou pular, e responder monta a
 * empresa e avisa o comercial.
 */
class QuestionarioTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['comercial.contato.email' => 'comercial@crm.test']);
    }

    /** @return array{0: Tenant, 1: User} */
    private function pendente(): array
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
            'experiencia' => 'nunca', 'cargo' => 'dono', 'ramo' => 'software', 'tamanho' => '2',
            'para_quem' => 'empresas', 'canais' => ['whatsapp', 'email'], 'origens' => ['indicacao'],
            'conheceu' => 'google', 'telefone' => null, 'dicas_whatsapp' => false,
        ], $extra);
    }

    private function emailsDoPerfil(): int
    {
        return Mail::queued(AvisoDeAssinaturaMail::class, fn ($m) => $m->tipo === 'comercial_perfil')->count();
    }

    public function test_cadastro_leva_ao_questionario(): void
    {
        $this->post('/register', ['name' => 'Ana Souza', 'empresa' => 'Acme Vendas', 'email' => 'ana@ex.test', 'password' => 'password'])
            ->assertRedirect(route('boas-vindas.show', absolute: false));

        $tenantId = User::firstWhere('email', 'ana@ex.test')->tenant_id;
        $this->assertTrue(PerfilDeEmpresa::pendente($tenantId));
    }

    public function test_admin_com_perfil_pendente_e_levado_ao_questionario(): void
    {
        [, $admin] = $this->pendente();

        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('boas-vindas.show'));
        $this->actingAs($admin)->get(route('configuracoes.assinatura'))->assertOk();
        $this->actingAs($admin)->get(route('boas-vindas.show'))->assertOk()
            ->assertInertia(fn ($p) => $p->component('BoasVindas/Questionario')->has('opcoes.ramos')->where('nome', explode(' ', $admin->name)[0]));
    }

    public function test_empresa_sem_linha_de_perfil_nunca_e_levada(): void
    {
        $t = $this->novoTenant();
        TenantBootstrapper::bootstrap($t);
        $admin = $this->agente($t, 'admin');

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->get(route('boas-vindas.show'))->assertRedirect(route('dashboard'));
    }

    public function test_vendedor_nao_e_levado_ao_questionario(): void
    {
        [$t] = $this->pendente();
        $vendedor = $this->agente($t, 'vendedor');

        $this->actingAs($vendedor)->get('/dashboard')->assertOk();
    }

    public function test_empresa_com_perfil_pulado_nao_e_levada(): void
    {
        [$t, $admin] = $this->pendente();
        PerfilDeEmpresa::daEmpresa($t->id)->forceFill(['pulado_em' => now()])->save();

        $this->actingAs($admin)->get('/dashboard')->assertOk();
    }

    public function test_responder_aplica_envia_email_e_vai_ao_dashboard(): void
    {
        [$t, $admin] = $this->pendente();

        $this->actingAs($admin)->post(route('boas-vindas.store'), $this->respostas())->assertRedirect(route('dashboard'));

        $this->assertNotNull(PerfilDeEmpresa::daEmpresa($t->id)->respondido_em);
        $this->assertSame(2, TarefaPadrao::where('user_id', $admin->id)->count());
        Mail::assertQueued(AvisoDeAssinaturaMail::class, function ($m) use ($t) {
            $texto = implode(' ', $m->conteudo()['paragrafos']);

            return $m->tipo === 'comercial_perfil' && $m->hasTo('comercial@crm.test')
                && str_contains($m->conteudo()['assunto'], $t->nome) && str_contains($texto, 'Software e tecnologia');
        });
        $this->actingAs($admin)->get('/dashboard')->assertOk();
    }

    public function test_responder_de_novo_nao_reaplica(): void
    {
        [, $admin] = $this->pendente();
        $this->actingAs($admin)->post(route('boas-vindas.store'), $this->respostas());

        $this->actingAs($admin)->post(route('boas-vindas.store'), $this->respostas(['canais' => ['ligacao']]))->assertRedirect(route('dashboard'));

        $this->assertSame(2, TarefaPadrao::where('user_id', $admin->id)->count());
        $this->assertSame(1, $this->emailsDoPerfil());
        $this->actingAs($admin)->get(route('boas-vindas.show'))->assertRedirect(route('dashboard'));
    }

    public function test_validacao_em_portugues(): void
    {
        [, $admin] = $this->pendente();

        $this->actingAs($admin)->from(route('boas-vindas.show'))->post(route('boas-vindas.store'), [])
            ->assertSessionHasErrors([
                'ramo' => 'Informe o ramo de atividade da sua empresa.',
                'canais' => 'Escolha ao menos um canal.',
                'origens' => 'Escolha ao menos uma origem.',
            ]);
    }

    public function test_telefone_mascarado_vira_e164(): void
    {
        [$t, $admin] = $this->pendente();

        $this->actingAs($admin)->post(route('boas-vindas.store'), $this->respostas(['telefone' => '(11) 99999-8888', 'dicas_whatsapp' => true]))
            ->assertRedirect(route('dashboard'));

        $this->assertSame('+5511999998888', PerfilDeEmpresa::daEmpresa($t->id)->telefone);
    }

    public function test_empresa_sem_linha_nao_e_personalizada(): void
    {
        $t = $this->novoTenant();
        TenantBootstrapper::bootstrap($t);
        $admin = $this->agente($t, 'admin');

        $this->actingAs($admin)->post(route('boas-vindas.store'), $this->respostas())->assertRedirect(route('dashboard'));
        $this->actingAs($admin)->post(route('boas-vindas.pular'))->assertRedirect(route('dashboard'));

        $this->assertNull(PerfilDeEmpresa::daEmpresa($t->id));
        $this->assertSame(0, TarefaPadrao::where('user_id', $admin->id)->count());
        $this->assertSame(0, $this->emailsDoPerfil());
    }

    public function test_pular_resolve_sem_email(): void
    {
        [$t, $admin] = $this->pendente();

        $this->actingAs($admin)->post(route('boas-vindas.pular'))->assertRedirect(route('dashboard'));

        $this->assertNotNull(PerfilDeEmpresa::daEmpresa($t->id)->pulado_em);
        $this->assertSame(0, $this->emailsDoPerfil());
    }

    public function test_vendedor_nao_responde(): void
    {
        [$t] = $this->pendente();
        $vendedor = $this->agente($t, 'vendedor');

        $this->actingAs($vendedor)->post(route('boas-vindas.store'), $this->respostas())->assertForbidden();
        $this->assertTrue(PerfilDeEmpresa::pendente($t->id));
    }
}
