<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A raiz não tem página própria: visitante vai para o login; quem está logado, para o dashboard. */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_raiz_redireciona_conforme_a_sessao(): void
    {
        $this->get('/')->assertRedirect('/login');

        $user = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
        $this->actingAs($user)->get('/')->assertRedirect('/dashboard');
    }
}
