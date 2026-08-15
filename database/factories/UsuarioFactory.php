<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Usuario>
 */
class UsuarioFactory extends Factory
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
            'nome' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'descricao' => fake()->optional()->sentence(),
            'telefone' => fake()->numberBetween(1000000000, 9999999999),
            'user_id' => User::factory()->create(['tenant_id' => $tenant->id])->id,
            'tenant_id' => $tenant->id,
        ];
    }

    /**
     * Reconcile the nested User's tenant with the final Usuario tenant.
     *
     * definition() cannot see attribute overrides passed to create()/state(),
     * so when a caller (e.g. ProjetoFactory) overrides 'tenant_id' to share a
     * single tenant across a fixture graph, the User created above still
     * carries the tenant that was generated internally. This hook re-syncs
     * the User to whatever tenant_id the Usuario actually ended up with.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Usuario $usuario) {
            User::whereKey($usuario->user_id)
                ->where('tenant_id', '!=', $usuario->tenant_id)
                ->update(['tenant_id' => $usuario->tenant_id]);
        });
    }
}
