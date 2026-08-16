<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Statu>
 */
class StatuFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'descricao' => fake()->word(),
            'ordem' => fake()->numberBetween(1, 10),
            'tenant_id' => Tenant::factory(),
        ];
    }

    /**
     * tenant_id não está em $fillable (Global Constraint da task de
     * multi-tenancy — o único jeito de setá-lo é atribuição direta de
     * propriedade ou a trait BelongsToTenant). Model::unguarded() é a mesma
     * técnica que o próprio Factory::createChildren() do framework usa para
     * popular atributos sem respeitar $fillable, e preserva tanto o default
     * de definition() (Tenant::factory() aninhado) quanto overrides
     * explícitos via ->create(['tenant_id' => ...]) usados nos testes.
     */
    public function newModel(array $attributes = [])
    {
        return Model::unguarded(fn () => parent::newModel($attributes));
    }
}
