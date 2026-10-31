<?php

namespace Tests\Feature\Seeders;

use App\Models\Assinatura;
use App\Models\Estagio;
use App\Models\Funil;
use App\Models\MotivoPerda;
use App\Models\StatusProjeto;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\ConfiguracaoPadraoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Configuração padrão de cada empresa: funil "Vendas" com seis estágios,
 * motivos de perda, status de projeto, teste grátis e um admin ativo. O
 * cadastro já cria tudo; o seeder completa empresas que ficaram sem algo
 * (anteriores ao recurso, importadas, consertadas à mão) e não mexe no que
 * a empresa já configurou.
 */
class ConfiguracaoPadraoSeederTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    /** @return array{funis:int, estagios:int, motivos:int, status:int, assinaturas:int} */
    private function contagem(Tenant $t): array
    {
        return [
            'funis' => Funil::withoutGlobalScopes()->withTrashed()->where('tenant_id', $t->id)->count(),
            'estagios' => Estagio::withoutGlobalScopes()->withTrashed()->where('tenant_id', $t->id)->count(),
            'motivos' => MotivoPerda::withoutGlobalScopes()->withTrashed()->where('tenant_id', $t->id)->count(),
            'status' => StatusProjeto::withoutGlobalScopes()->withTrashed()->where('tenant_id', $t->id)->count(),
            'assinaturas' => Assinatura::where('tenant_id', $t->id)->count(),
        ];
    }

    private function semear(): void
    {
        app(CurrentTenant::class)->clear();
        $this->seed(ConfiguracaoPadraoSeeder::class);
    }

    public function test_empresa_sem_configuracao_ganha_funil_estagios_motivos_status_e_teste_gratis(): void
    {
        $tenant = $this->novoTenant();

        $this->semear();

        $this->assertSame(['funis' => 1, 'estagios' => 6, 'motivos' => 7, 'status' => 7, 'assinaturas' => 1], $this->contagem($tenant));
        $funil = Funil::withoutGlobalScopes()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame(['Vendas', true], [$funil->nome, (bool) $funil->is_default]);
        $tipos = Estagio::withoutGlobalScopes()->where('funil_id', $funil->id)->pluck('tipo')->countBy()->all();
        $this->assertSame(['aberto' => 4, 'ganho' => 1, 'perdido' => 1], collect($tipos)->sortKeys()->all());
        $this->assertSame('teste', Assinatura::where('tenant_id', $tenant->id)->value('status'));
    }

    public function test_nao_mexe_no_que_a_empresa_ja_configurou_e_rodar_de_novo_nao_duplica(): void
    {
        $tenant = $this->novoTenant();
        $this->semear();
        Funil::withoutGlobalScopes()->where('tenant_id', $tenant->id)->update(['nome' => 'Comercial']);
        $antes = $this->contagem($tenant);

        $this->semear();
        $this->semear();

        $this->assertSame($antes, $this->contagem($tenant));
        $this->assertSame('Comercial', Funil::withoutGlobalScopes()->where('tenant_id', $tenant->id)->value('nome'));
    }

    public function test_completa_so_o_que_falta(): void
    {
        $tenant = $this->novoTenant();
        Funil::factory()->padrao()->create(['tenant_id' => $tenant->id, 'nome' => 'Parcerias']);

        $this->semear();

        $contagem = $this->contagem($tenant);
        $this->assertSame([1, 7, 7], [$contagem['funis'], $contagem['motivos'], $contagem['status']]);
        $this->assertSame('Parcerias', Funil::withoutGlobalScopes()->where('tenant_id', $tenant->id)->value('nome'));
    }

    public function test_empresa_que_arquivou_todos_os_funis_nao_ganha_outro(): void
    {
        $tenant = $this->novoTenant();
        $arquivado = Funil::factory()->create(['tenant_id' => $tenant->id]);
        $arquivado->delete();

        $this->semear();

        $this->assertSame(0, Funil::withoutGlobalScopes()->where('tenant_id', $tenant->id)->whereNull('deleted_at')->count());
        $this->assertSame(1, Funil::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public function test_cada_empresa_recebe_a_propria_configuracao(): void
    {
        $a = $this->novoTenant();
        $b = $this->novoTenant();

        $this->semear();

        foreach ([$a, $b] as $t) {
            $this->assertSame(['funis' => 1, 'estagios' => 6, 'motivos' => 7, 'status' => 7, 'assinaturas' => 1], $this->contagem($t));
        }
        $funilDeA = Funil::withoutGlobalScopes()->where('tenant_id', $a->id)->value('id');
        $this->assertSame(0, Estagio::withoutGlobalScopes()->where('funil_id', $funilDeA)->where('tenant_id', '!=', $a->id)->count());
    }

    public function test_empresa_sem_admin_ativo_ganha_o_usuario_ativo_mais_antigo_como_admin(): void
    {
        $tenant = $this->novoTenant();
        $inativo = User::factory()->create(['tenant_id' => $tenant->id]);
        $inativo->forceFill(['ativo' => false])->save();
        $maisAntigo = $this->agente($tenant);
        $outro = $this->agente($tenant);

        $this->semear();

        setPermissionsTeamId($tenant->id);
        $this->assertTrue($maisAntigo->fresh()->hasRole('admin'));
        $this->assertFalse($outro->fresh()->hasRole('admin'));
        $this->assertFalse($inativo->fresh()->hasRole('admin'));
    }

    public function test_empresa_com_admin_ativo_nao_ganha_outro(): void
    {
        $tenant = $this->novoTenant();
        $vendedor = $this->agente($tenant);
        $admin = $this->agente($tenant, 'admin');

        $this->semear();

        setPermissionsTeamId($tenant->id);
        $this->assertFalse($vendedor->fresh()->hasRole('admin'));
        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_cadastro_cria_a_configuracao_uma_vez_e_o_seeder_nao_duplica(): void
    {
        $this->post('/register', ['name' => 'Ana', 'empresa' => 'Acme', 'email' => 'ana@ex.test', 'password' => 'password']);
        $tenant = Tenant::findOrFail(User::firstWhere('email', 'ana@ex.test')->tenant_id);
        $antes = $this->contagem($tenant);

        $this->semear();

        $this->assertSame(['funis' => 1, 'estagios' => 6, 'motivos' => 7, 'status' => 7, 'assinaturas' => 1], $antes);
        $this->assertSame($antes, $this->contagem($tenant));
    }
}
