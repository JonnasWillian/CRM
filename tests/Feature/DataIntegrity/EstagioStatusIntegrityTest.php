<?php

namespace Tests\Feature\DataIntegrity;

use App\Models\Estagio;
use App\Models\Projeto;
use App\Models\Statu;
use App\Models\Tenant;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstagioStatusIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleting_an_estagio_in_use_keeps_the_lead_accessible(): void
    {
        $tenant = Tenant::factory()->create();
        $estagio = Estagio::factory()->create(['tenant_id' => $tenant->id]);
        $usuario = Usuario::factory()->create(['tenant_id' => $tenant->id, 'estagio_id' => $estagio->id]);

        app(CurrentTenant::class)->set($tenant);

        $estagio->delete(); // soft delete

        $this->assertNotNull($usuario->fresh()->estagio);
        $this->assertEquals($estagio->id, $usuario->fresh()->estagio->id);
    }

    public function test_physically_deleting_an_estagio_in_use_throws(): void
    {
        $estagio = Estagio::factory()->create();
        Usuario::factory()->create(['estagio_id' => $estagio->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $estagio->forceDelete();
    }

    public function test_physically_deleting_a_status_in_use_throws(): void
    {
        $status = Statu::factory()->create();
        Projeto::factory()->create(['status_id' => $status->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $status->forceDelete();
    }
}
