<?php

namespace Tests\Feature\Autorizacao;

use App\Models\TarefaPadrao;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDOException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Modelo de tarefa tem dono direto: `tarefa_padroes.user_id`.
 */
class TarefaPadraoPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User, 2: User, 3: TarefaPadrao}
     */
    private function cenario(): array
    {
        $tenant = Tenant::factory()->create();
        $dono = User::factory()->create(['tenant_id' => $tenant->id]);
        $intruso = User::factory()->create(['tenant_id' => $tenant->id]);

        app(CurrentTenant::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
        $dono->assignRole('vendedor');
        $intruso->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modelo = new TarefaPadrao();
        $modelo->fill(['user_id' => $dono->id, 'titulo' => 'Follow-up', 'prazo_dias' => 3]);
        $modelo->tenant_id = $tenant->id;
        $modelo->save();

        return [$tenant, $dono, $intruso, $modelo];
    }

    /**
     * O `catch (\Exception)` que engolia a falha real de banco.
     *
     * `update()` e `destroy()` envolviam tudo — inclusive o `authorize()` — num
     * catch amplo que respondia `{"error":"Modelo não encontrado"}` com 404.
     * Uma QueryException no `delete()` chegava ao usuário como "não
     * encontrado": a linha continuava lá, a remoção não aconteceu, e ninguém
     * ficava sabendo. O catch existia para um `findOrFail` que não existe mais
     * — hoje quem resolve o modelo é o route model binding.
     *
     * A falha é injetada no evento `deleting`, que é onde o soft delete
     * escreve. Um trigger de banco provaria o mesmo, mas DDL causa commit
     * implícito no MySQL e quebraria o isolamento transacional do
     * RefreshDatabase para os testes seguintes.
     */
    public function test_falha_de_banco_no_delete_nao_vira_404(): void
    {
        [, $dono, , $modelo] = $this->cenario();

        TarefaPadrao::deleting(function () {
            throw new QueryException(
                'mysql',
                'update `tarefa_padroes` set `deleted_at` = ? where `id` = ?',
                [],
                new PDOException('SQLSTATE[HY000]: falha real de banco'),
            );
        });

        $resposta = $this->actingAs($dono)->deleteJson("/api/tarefa-padroes/{$modelo->id}");

        $this->assertSame(500, $resposta->status(), 'erro de banco nao pode ser reembalado como 404');
        $this->assertStringNotContainsString('Modelo n', (string) $resposta->getContent());

        // E a linha continua lá — que é justamente o que o 404 escondia.
        $this->assertDatabaseHas('tarefa_padroes', ['id' => $modelo->id, 'deleted_at' => null]);
    }

    /** O caminho feliz do delete não pode ter ido junto com o catch. */
    public function test_o_dono_continua_removendo_o_proprio_modelo(): void
    {
        [, $dono, , $modelo] = $this->cenario();

        $this->actingAs($dono)->deleteJson("/api/tarefa-padroes/{$modelo->id}")->assertOk();

        $this->assertSoftDeleted('tarefa_padroes', ['id' => $modelo->id]);
    }

    /**
     * Com o `authorize()` fora do try, a negativa da policy sai pela exceção e
     * ganha o corpo normalizado de bootstrap/app.php, em vez da quarta
     * assinatura de 404 que o catch fabricava.
     */
    public function test_a_negativa_da_policy_usa_o_corpo_normalizado(): void
    {
        [, , $intruso, $modelo] = $this->cenario();

        foreach ([
            $this->actingAs($intruso)->putJson("/api/tarefa-padroes/{$modelo->id}", ['titulo' => 'X', 'prazo_dias' => 1]),
            $this->actingAs($intruso)->deleteJson("/api/tarefa-padroes/{$modelo->id}"),
        ] as $resposta) {
            $resposta->assertNotFound();
            $this->assertSame('{"message":"Not Found"}', $resposta->getContent());
        }
    }

    public function test_intruso_nao_edita_modelo_de_colega(): void
    {
        $tenant = Tenant::factory()->create();
        $dono = User::factory()->create(['tenant_id' => $tenant->id]);
        $intruso = User::factory()->create(['tenant_id' => $tenant->id]);

        app(CurrentTenant::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
        $dono->assignRole('vendedor');
        $intruso->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modelo = new TarefaPadrao();
        $modelo->fill(['user_id' => $dono->id, 'titulo' => 'Follow-up', 'prazo_dias' => 3]);
        $modelo->tenant_id = $tenant->id;
        $modelo->save();

        $this->actingAs($intruso)
            ->putJson("/api/tarefa-padroes/{$modelo->id}", ['titulo' => 'Sequestrado', 'prazo_dias' => 1])
            ->assertNotFound();

        $this->assertSame('Follow-up', $modelo->fresh()->titulo);

        $this->actingAs($dono)
            ->putJson("/api/tarefa-padroes/{$modelo->id}", ['titulo' => 'Follow-up 2', 'prazo_dias' => 1])
            ->assertOk();
    }
}
