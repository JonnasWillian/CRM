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
        return [
            'nome' => fake()->sentence(3),
            'descricao' => fake()->optional()->paragraph(),
            'usuario_id' => Usuario::factory(),
            'status_id' => Statu::factory(),
            'tenant_id' => Tenant::factory(),
        ];
    }
}
