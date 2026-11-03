<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nome' => fake()->company(),
            // Como o cadastro (RegisteredUserController): slug do nome mais sufixo
            // aleatório. Sortear um segundo nome de empresa colidia no índice único
            // de vez em quando (o unique() do Faker é zerado a cada factory).
            'slug' => fn (array $atributos) => Str::slug($atributos['nome']).'-'.Str::random(6),
            'plano' => null,
        ];
    }
}
