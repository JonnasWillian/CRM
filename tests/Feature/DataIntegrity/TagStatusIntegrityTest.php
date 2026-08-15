<?php

namespace Tests\Feature\DataIntegrity;

use App\Models\Projeto;
use App\Models\Statu;
use App\Models\Tags;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagStatusIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_soft_deleting_a_tag_in_use_keeps_the_lead_accessible(): void
    {
        $tag = Tags::factory()->create();
        $usuario = Usuario::factory()->create(['tag_id' => $tag->id]);

        $tag->delete(); // soft delete

        $this->assertNotNull($usuario->fresh()->tag);
        $this->assertEquals($tag->id, $usuario->fresh()->tag->id);
    }

    public function test_physically_deleting_a_tag_in_use_throws(): void
    {
        $tag = Tags::factory()->create();
        Usuario::factory()->create(['tag_id' => $tag->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $tag->forceDelete();
    }

    public function test_physically_deleting_a_status_in_use_throws(): void
    {
        $status = Statu::factory()->create();
        Projeto::factory()->create(['status_id' => $status->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $status->forceDelete();
    }
}
