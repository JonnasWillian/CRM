<?php

namespace Database\Factories;

use App\Models\Statu;
use App\Models\Tenant;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Projeto>
 */
class ProjetoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'nome' => fake()->sentence(3),
            'descricao' => fake()->optional()->paragraph(),
            'usuario_id' => Usuario::factory()->create(['tenant_id' => $tenant->id])->id,
            'status_id' => Statu::factory()->create(['tenant_id' => $tenant->id])->id,
            'tenant_id' => $tenant->id,
        ];
    }
}
