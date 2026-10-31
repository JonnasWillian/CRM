<?php

namespace Tests\Feature\Seeders;

use App\Models\Anotacao;
use App\Models\Estagio;
use App\Models\Perda;
use App\Models\Projeto;
use App\Models\Tarefa;
use App\Models\TarefaPadrao;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemonstracaoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Empresa de demonstração para desenvolvimento: três usuários, um por papel,
 * e dados que acendem todas as telas (pipeline com vários estágios, ganho,
 * perda com motivo, contrato recorrente, tarefas de cada situação). Nunca
 * roda em produção.
 */
class DemonstracaoSeederTest extends TestCase
{
    use RefreshDatabase;

    private function demo(): ?Tenant
    {
        return Tenant::where('slug', DemonstracaoSeeder::SLUG)->first();
    }

    /** Conta registros da empresa de demonstração, sem depender do tenant ativo. */
    private function daDemo(string $modelo): \Illuminate\Database\Eloquent\Builder
    {
        return $modelo::withoutGlobalScopes()->where('tenant_id', $this->demo()->id);
    }

    private function semear(): void
    {
        app(CurrentTenant::class)->clear();
        $this->seed(DemonstracaoSeeder::class);
        app(CurrentTenant::class)->clear();
    }

    public function test_cria_um_usuario_por_papel_e_todos_conseguem_entrar(): void
    {
        $this->semear();

        foreach (DemonstracaoSeeder::USUARIOS as $email => [$nome, $papel]) {
            $user = User::firstWhere('email', $email);
            $this->assertNotNull($user, $email);
            setPermissionsTeamId($user->tenant_id);
            $this->assertTrue($user->hasRole($papel), "{$email} deveria ser {$papel}");

            $this->post('/login', ['email' => $email, 'password' => DemonstracaoSeeder::SENHA])->assertRedirect(route('dashboard', absolute: false));
            $this->get('/dashboard')->assertOk();
            $this->post('/logout');
        }
    }

    public function test_os_dados_acendem_pipeline_ganho_perda_recorrencia_e_tarefas(): void
    {
        Carbon::setTestNow('2026-10-29 10:00:00');
        $this->semear();

        $leads = $this->daDemo(Usuario::class)->get();
        $this->assertGreaterThanOrEqual(10, $leads->count());
        $this->assertGreaterThanOrEqual(4, $leads->pluck('estagio_id')->unique()->count());
        $this->assertGreaterThanOrEqual(2, $leads->pluck('user_id')->unique()->count(), 'a carteira deve estar dividida entre vendedores');

        $ganho = $leads->first(fn ($l) => Estagio::withoutGlobalScopes()->find($l->estagio_id)?->tipo === Estagio::TIPO_GANHO);
        $this->assertNotNull($ganho?->valor_fechado);
        $this->assertNotNull($ganho->fechado_em);
        $this->assertSame(1, $this->daDemo(Perda::class)->whereNotNull('motivo_perda_id')->count());

        $recorrente = $this->daDemo(Projeto::class)->where('tipo', 'recorrente')->first();
        $this->assertNotNull($recorrente);
        $this->assertGreaterThan(0, (float) $recorrente->preco);
        $this->assertSame(1, \App\Models\ProjetoAditivo::withoutGlobalScopes()->where('projeto_id', $recorrente->id)->count());
        $this->assertGreaterThanOrEqual(1, $this->daDemo(Projeto::class)->where('tipo', 'unico')->count());

        $tarefas = $this->daDemo(Tarefa::class)->get();
        $this->assertTrue($tarefas->contains(fn ($t) => ! $t->concluido && $t->data_limite->isPast() && ! $t->data_limite->isToday()), 'atrasada');
        $this->assertTrue($tarefas->contains(fn ($t) => ! $t->concluido && $t->data_limite->isToday()), 'hoje');
        $this->assertTrue($tarefas->contains(fn ($t) => ! $t->concluido && $t->data_limite->isFuture()), 'futura');
        $this->assertTrue($tarefas->contains(fn ($t) => $t->concluido), 'concluída');

        $this->assertGreaterThanOrEqual(2, $this->daDemo(Anotacao::class)->count());
        $this->assertGreaterThanOrEqual(2, $this->daDemo(TarefaPadrao::class)->count());
    }

    public function test_dashboard_e_relatorios_carregam_com_os_dados_da_demonstracao(): void
    {
        $this->semear();
        $admin = User::firstWhere('email', 'admin@demo.test');

        $this->actingAs($admin)->get('/dashboard')->assertOk();
        $this->actingAs($admin)->getJson('/api/metricas')->assertOk()->assertJsonPath('estado.leads_ativos', fn ($n) => $n > 0);
        $this->actingAs($admin)->getJson('/api/kanban')->assertOk();
        $this->actingAs($admin)->get('/relatorios')->assertOk();
    }

    public function test_rodar_de_novo_nao_duplica(): void
    {
        $this->semear();
        $leads = $this->daDemo(Usuario::class)->count();

        $this->semear();

        $this->assertSame(1, Tenant::where('slug', DemonstracaoSeeder::SLUG)->count());
        $this->assertSame($leads, $this->daDemo(Usuario::class)->count());
        $this->assertSame(3, User::where('tenant_id', $this->demo()->id)->count());
    }

    public function test_nao_roda_em_producao(): void
    {
        $this->app['env'] = 'production';

        // Em produção o db:seed pede confirmação; --force é como alguém rodaria de fato.
        $this->artisan('db:seed', ['--class' => DemonstracaoSeeder::class, '--force' => true])
            ->expectsOutputToContain('não roda em produção')->assertSuccessful();

        $this->assertNull($this->demo());
        $this->assertNull(User::firstWhere('email', 'admin@demo.test'));
    }

    public function test_database_seeder_configura_tudo_e_so_cria_a_demonstracao_em_ambiente_local(): void
    {
        $outra = Tenant::factory()->create();

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertNull($this->demo(), 'fora do ambiente local não há demonstração');
        $this->assertSame(1, \App\Models\Funil::withoutGlobalScopes()->where('tenant_id', $outra->id)->count());
        $this->assertSame(3, \Spatie\Permission\Models\Role::count());

        $this->app['env'] = 'local';
        $this->seed(DatabaseSeeder::class);
        $this->assertNotNull($this->demo());
    }
}
