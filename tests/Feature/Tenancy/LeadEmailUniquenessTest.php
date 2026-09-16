<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A unicidade de usuarios.email é por tenant, não global.
 *
 * Regras `unique:` do Laravel consultam o banco diretamente e não passam
 * pelo Eloquent, portanto ignoram o TenantScope. Sem escopo explícito na
 * regra (e sem índice composto no banco), o mesmo email cadastrado por um
 * tenant bloqueia todos os outros — e a mensagem de erro revela que aquele
 * email já existe em algum lugar do sistema.
 */
class LeadEmailUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private function tenantComStaff(): array
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);

        return [$tenant, $staff];
    }

    public function test_tenant_pode_cadastrar_lead_com_email_ja_usado_em_outro_tenant(): void
    {
        [$tenantA, $staffA] = $this->tenantComStaff();
        [, $staffB] = $this->tenantComStaff();

        app(CurrentTenant::class)->set($tenantA);
        Usuario::factory()->create([
            'user_id' => $staffA->id,
            'tenant_id' => $tenantA->id,
            'email' => 'contato@cliente.com',
        ]);

        $resposta = $this->actingAs($staffB)->postJson('/api/usuarios', [
            'nome' => 'Cliente Compartilhado',
            'email' => 'contato@cliente.com',
            'telefone' => '11999998888',
        ]);

        $resposta->assertCreated();
    }

    public function test_email_duplicado_dentro_do_mesmo_tenant_continua_bloqueado(): void
    {
        [$tenant, $staff] = $this->tenantComStaff();

        app(CurrentTenant::class)->set($tenant);
        Usuario::factory()->create([
            'user_id' => $staff->id,
            'tenant_id' => $tenant->id,
            'email' => 'duplicado@cliente.com',
        ]);

        $resposta = $this->actingAs($staff)->postJson('/api/usuarios', [
            'nome' => 'Cliente Duplicado',
            'email' => 'duplicado@cliente.com',
            'telefone' => '11999998888',
        ]);

        $resposta->assertStatus(422);
        $resposta->assertJsonValidationErrors('email');
    }

    public function test_lead_pode_ser_atualizado_mantendo_o_proprio_email(): void
    {
        [$tenant, $staff] = $this->tenantComStaff();

        app(CurrentTenant::class)->set($tenant);
        $lead = Usuario::factory()->create([
            'user_id' => $staff->id,
            'tenant_id' => $tenant->id,
            'email' => 'lead@cliente.com',
        ]);

        // Sem 'id' no corpo: o identificador autoritativo é o da rota.
        $resposta = $this->actingAs($staff)->putJson("/api/usuarios/{$lead->id}", [
            'nome' => 'Nome Atualizado',
            'email' => 'lead@cliente.com',
            'telefone' => '11999998888',
        ]);

        $resposta->assertOk();
        $this->assertSame('Nome Atualizado', $lead->fresh()->nome);
    }
}
