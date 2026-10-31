<?php

namespace Tests\Feature\Seeders;

use App\Support\Autorizacao\PapeisPadrao;
use Database\Seeders\PapeisEPermissoesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Papéis e permissões nascem na migration 2026_09_22_100008; o seeder é o
 * jeito de restaurá-los sem refazer o banco. Os dois seguem o mesmo contrato
 * (PapeisPadrao) — este teste quebra se um deles mudar sem o outro.
 */
class PapeisEPermissoesSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, list<string>> papel => permissões, como estão no banco */
    private function noBanco(): array
    {
        return Role::with('permissions')->orderBy('name')->get()
            ->mapWithKeys(fn (Role $r) => [$r->name => $r->permissions->pluck('name')->sort()->values()->all()])
            ->all();
    }

    /** @return array<string, list<string>> */
    private function contrato(): array
    {
        $papeis = collect(PapeisPadrao::PAPEIS)->map(fn (array $p) => collect($p)->sort()->values()->all())->all();
        ksort($papeis);

        return $papeis;
    }

    public function test_a_migration_cria_exatamente_o_contrato_de_papeis_e_permissoes(): void
    {
        $this->assertSame($this->contrato(), $this->noBanco());
        $this->assertEqualsCanonicalizing(PapeisPadrao::PERMISSOES, Permission::pluck('name')->all());
    }

    public function test_papeis_sao_globais_e_valem_para_qualquer_empresa(): void
    {
        $this->seed(PapeisEPermissoesSeeder::class);

        $this->assertSame(0, Role::whereNotNull('tenant_id')->count());
        $this->assertTrue(Role::pluck('guard_name')->every(fn ($g) => $g === 'web'));
    }

    public function test_rodar_de_novo_nao_duplica_e_repara_o_que_foi_mexido(): void
    {
        // Alguém tirou uma permissão do gestor, apagou outra e deu uma a mais ao vendedor.
        Role::findByName('gestor')->revokePermissionTo('configuracoes.manage');
        Permission::findByName('agentes.manage')->delete();
        Role::findByName('vendedor')->givePermissionTo('leads.view-all');

        $this->seed(PapeisEPermissoesSeeder::class);
        $this->seed(PapeisEPermissoesSeeder::class);

        $this->assertSame($this->contrato(), $this->noBanco());
        $this->assertSame(count(PapeisPadrao::PERMISSOES), Permission::count());
        $this->assertSame(count(PapeisPadrao::PAPEIS), Role::count());
    }

    public function test_restaura_papeis_e_permissoes_num_banco_sem_eles(): void
    {
        DB::table('role_has_permissions')->delete();
        DB::table('model_has_roles')->delete();
        Role::query()->delete();
        Permission::query()->delete();

        $this->seed(PapeisEPermissoesSeeder::class);

        $this->assertSame($this->contrato(), $this->noBanco());
    }
}
