<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Funil>
 */
class FunilFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => ucfirst(fake()->unique()->words(2, true)),
            'descricao' => fake()->optional()->sentence(),
            'ordem' => 1,
            'tenant_id' => Tenant::factory(),
        ];
    }

    public function padrao(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    /**
     * `tenant_id` e `is_default` não estão em $fillable (ver Funil). Mesma
     * técnica das demais factories do projeto: Model::unguarded() é o que o
     * próprio Factory::createChildren() do framework usa, e preserva tanto os
     * defaults de definition() quanto overrides passados em ->create().
     */
    public function newModel(array $attributes = [])
    {
        return Model::unguarded(fn () => parent::newModel($attributes));
    }
}
