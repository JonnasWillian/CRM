<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_query_leaks_across_tenants_without_explicit_filtering(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $staffA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $staffB = User::factory()->create(['tenant_id' => $tenantB->id]);

        app(CurrentTenant::class)->set($tenantA);
        $leadA = Usuario::factory()->create(['user_id' => $staffA->id, 'tenant_id' => $tenantA->id]);

        app(CurrentTenant::class)->set($tenantB);
        $leadB = Usuario::factory()->create(['user_id' => $staffB->id, 'tenant_id' => $tenantB->id]);

        // Caminho HTTP real, passando por auth+tenant middleware e pelo controller já hardened (Task 14)
        $response = $this->actingAs($staffA)->postJson('/api/pegarUsuarios');
        $response->assertOk();
        $response->assertJsonFragment(['id' => $leadA->id]);
        $response->assertJsonMissing(['id' => $leadB->id]);

        // Prova isolada do scope, sem passar por controller nenhum
        app(CurrentTenant::class)->set($tenantA);
        $this->assertEqualsCanonicalizing([$leadA->id], Usuario::pluck('id')->all());

        app(CurrentTenant::class)->set($tenantB);
        $this->assertEqualsCanonicalizing([$leadB->id], Usuario::pluck('id')->all());

        // Fail-closed: sem tenant setado, deve lançar, nunca retornar tudo sem filtro
        app(CurrentTenant::class)->clear();
        $this->expectException(\RuntimeException::class);
        Usuario::all();
    }
}
