<?php

namespace Database\Factories;

use App\Models\Statu;
use App\Models\Tenant;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

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

    /**
     * tenant_id não está em $fillable (Global Constraint da task de
     * multi-tenancy — o único jeito de setá-lo é atribuição direta de
     * propriedade ou a trait BelongsToTenant). Model::unguarded() é a mesma
     * técnica que o próprio Factory::createChildren() do framework usa para
     * popular atributos sem respeitar $fillable, e preserva tanto o default
     * de definition() quanto overrides explícitos via
     * ->create(['tenant_id' => ...]) usados nos testes.
     */
    public function newModel(array $attributes = [])
    {
        return Model::unguarded(fn () => parent::newModel($attributes));
    }
}
