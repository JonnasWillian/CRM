<?php

namespace Tests\Feature\Navegacao;

use App\Models\Funil;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Toda tela autenticada responde 200.
 *
 * O `npm run build` valida sintaxe, não renderização: um `route()` para um
 * nome que não existe mais, uma prop obrigatória que ninguém passa ou um
 * middleware mal ordenado passam pelo build e quebram na cara do usuário.
 *
 * Este teste é a rede que o build não é. Ele não olha para o layout — só
 * garante que cada porta abre.
 */
class TodasAsTelasAbremTest extends TestCase
{
    use RefreshDatabase;

    public function test_toda_tela_autenticada_responde_200(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        app(CurrentTenant::class)->set($tenant);

        setPermissionsTeamId($tenant->id);
        $admin->assignRole('admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $lead = Usuario::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
            'funil_id' => $funil->id,
        ]);

        $telas = [
            'dashboard' => '/dashboard',
            'kanban' => '/kanban',
            'modelos' => '/modelos-tarefa',
            'perfil do lead' => "/leads/{$lead->id}",
            'funis' => '/configuracoes/funis',
            'motivos de perda' => '/configuracoes/motivos-perda',
            'por que perdemos' => '/relatorios/perdas',
            'conta' => '/profile',
        ];

        $quebradas = [];
        foreach ($telas as $nome => $url) {
            $status = $this->actingAs($admin)->get($url)->status();
            if ($status !== 200) {
                $quebradas[] = "{$nome} ({$url}) -> {$status}";
            }
        }

        $this->assertSame([], $quebradas, "telas que não abriram:\n  ".implode("\n  ", $quebradas));
    }

    /**
     * Quem não tem a permissão não alcança a tela — e recebe 403, não uma
     * página meio renderizada.
     */
    public function test_vendedor_nao_alcanca_as_telas_restritas(): void
    {
        $tenant = Tenant::factory()->create();
        $vendedor = User::factory()->create(['tenant_id' => $tenant->id]);
        app(CurrentTenant::class)->set($tenant);

        setPermissionsTeamId($tenant->id);
        $vendedor->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['/configuracoes/funis', '/configuracoes/motivos-perda', '/relatorios/perdas'] as $url) {
            $this->actingAs($vendedor)->get($url)->assertForbidden();
        }
    }
}
