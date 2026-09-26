<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MotivoPerda>
 */
class MotivoPerdaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'descricao' => ucfirst(fake()->unique()->words(2, true)),
            'ordem' => 1,
            'tenant_id' => Tenant::factory(),
        ];
    }

    /**
     * tenant_id não está em $fillable (Global Constraint da task de
     * multi-tenancy). Mesma técnica das demais factories do projeto.
     */
    public function newModel(array $attributes = [])
    {
        return Model::unguarded(fn () => parent::newModel($attributes));
    }
}
