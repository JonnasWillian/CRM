<?php

namespace Database\Factories;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Estagio>
 */
class EstagioFactory extends Factory
{
    public function definition(): array
    {
        $tenant = Tenant::factory()->create();

        return [
            'descricao' => fake()->word(),
            'ordem' => fake()->numberBetween(1, 10),
            'tipo' => Estagio::TIPO_ABERTO,
            'tenant_id' => $tenant->id,
            'funil_id' => Funil::factory()->create(['tenant_id' => $tenant->id])->id,
        ];
    }

    public function ganho(): static
    {
        return $this->state(fn () => ['tipo' => Estagio::TIPO_GANHO]);
    }

    public function perdido(): static
    {
        return $this->state(fn () => ['tipo' => Estagio::TIPO_PERDIDO]);
    }

    /**
     * Reconcilia o tenant do Funil aninhado com o tenant final do Estágio.
     *
     * Mesmo problema que UsuarioFactory::configure() resolve: definition() não
     * enxerga os overrides passados a create(), então quando o teste fixa um
     * 'tenant_id' para compartilhar um tenant entre os fixtures, o Funil criado
     * acima ainda carrega o tenant gerado internamente — e o estágio ficaria
     * num funil de outra empresa.
     *
     * Via query builder, e não Eloquent, porque Funil tem TenantScope: uma
     * consulta pelo model exigiria um CurrentTenant ativo, que a factory não
     * tem obrigação de ter.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Estagio $estagio) {
            DB::table('funis')
                ->where('id', $estagio->funil_id)
                ->where('tenant_id', '!=', $estagio->tenant_id)
                ->update(['tenant_id' => $estagio->tenant_id]);
        });
    }

    /**
     * tenant_id não está em $fillable (Global Constraint da task de
     * multi-tenancy). Ver comentário equivalente em UsuarioFactory.
     */
    public function newModel(array $attributes = [])
    {
        return Model::unguarded(fn () => parent::newModel($attributes));
    }
}
