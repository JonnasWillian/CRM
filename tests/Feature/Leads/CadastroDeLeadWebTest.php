<?php

namespace Tests\Feature\Leads;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/** O modal "Novo lead" da grade posta na rota web: erros na sessão, sucesso em flash. */
class CadastroDeLeadWebTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_cadastra_e_volta_para_a_grade_com_flash(): void
    {
        $tenant = $this->novoTenant();
        $vendedor = $this->agente($tenant);
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $primeiro = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 1]);

        $this->actingAs($vendedor)->from('/leads?sort=nome')
            ->post('/leads', ['nome' => 'Ana Souza', 'email' => 'ana@example.test', 'valor_estimado' => 1500])
            ->assertRedirect('/leads?sort=nome')
            ->assertSessionHas('sucesso');

        $lead = Usuario::firstWhere('email', 'ana@example.test');
        $this->assertSame($vendedor->id, $lead->user_id);
        $this->assertSame($funil->id, $lead->funil_id);
        $this->assertSame($primeiro->id, $lead->estagio_id);
        $this->assertSame('1500.00', $lead->valor_estimado);
    }

    public function test_salvar_e_abrir_vai_para_o_perfil(): void
    {
        $tenant = $this->novoTenant();
        $vendedor = $this->agente($tenant);
        Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);

        $resposta = $this->actingAs($vendedor)->post('/leads', ['nome' => 'Bruno Lima', 'email' => 'bruno@example.test', 'abrir' => 1]);
        $lead = Usuario::firstWhere('email', 'bruno@example.test');

        $resposta->assertRedirect("/leads/{$lead->id}");
    }

    public function test_erros_voltam_na_sessao(): void
    {
        $tenant = $this->novoTenant();
        $vendedor = $this->agente($tenant);

        $this->actingAs($vendedor)->from('/leads')->post('/leads', ['nome' => 'Ab', 'email' => 'x'])
            ->assertRedirect('/leads')
            ->assertSessionHasErrors(['nome', 'email']);
    }

    public function test_vendedor_nao_escolhe_responsavel_gestor_escolhe(): void
    {
        $tenant = $this->novoTenant();
        $vendedor = $this->agente($tenant);
        $gestor = $this->agente($tenant, 'gestor');
        Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($vendedor)->post('/leads', ['nome' => 'Carla Dias', 'email' => 'carla@example.test', 'user_id' => $gestor->id]);
        $this->assertSame($vendedor->id, Usuario::firstWhere('email', 'carla@example.test')->user_id);

        $this->actingAs($gestor)->post('/leads', ['nome' => 'Diego Melo', 'email' => 'diego@example.test', 'user_id' => $vendedor->id]);
        $this->assertSame($vendedor->id, Usuario::firstWhere('email', 'diego@example.test')->user_id);
    }
}
