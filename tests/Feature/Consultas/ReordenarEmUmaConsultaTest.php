<?php

namespace Tests\Feature\Consultas;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\MotivoPerda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Reordenar N itens é um único UPDATE, não N.
 */
class ReordenarEmUmaConsultaTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    public function test_reordenar_funis_faz_um_unico_update(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $ids = Funil::factory()->count(5)->create(['tenant_id' => $tenant->id])->pluck('id')->reverse()->values()->all();

        $updates = $this->updatesDe(fn () => $this->actingAs($admin)->patchJson('/api/funis/reordenar', ['ids' => $ids])->assertOk());

        $this->assertSame(1, $updates);
        $this->assertSame($ids, Funil::ordenados()->pluck('id')->all());
    }

    public function test_reordenar_estagios_faz_um_unico_update(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $funil = Funil::factory()->create(['tenant_id' => $tenant->id]);
        $ids = Estagio::factory()->count(4)->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id])
            ->pluck('id')->reverse()->values()->all();

        $updates = $this->updatesDe(fn () => $this->actingAs($admin)
            ->patchJson("/api/funis/{$funil->id}/estagios/reordenar", ['ids' => $ids])->assertOk());

        $this->assertSame(1, $updates);
        $this->assertSame($ids, Estagio::where('funil_id', $funil->id)->orderBy('ordem')->pluck('id')->all());
    }

    public function test_reordenar_motivos_faz_um_unico_update(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $ids = MotivoPerda::factory()->count(5)->create(['tenant_id' => $tenant->id])->pluck('id')->reverse()->values()->all();

        $updates = $this->updatesDe(fn () => $this->actingAs($admin)->patchJson('/api/motivos-perda/reordenar', ['ids' => $ids])->assertOk());

        $this->assertSame(1, $updates);
        $this->assertSame($ids, MotivoPerda::ordenados()->pluck('id')->all());
    }

    private function updatesDe(callable $acao): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $acao();

        $updates = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_starts_with(strtolower(ltrim($q['query'])), 'update'))
            ->count();
        DB::disableQueryLog();

        return $updates;
    }
}
