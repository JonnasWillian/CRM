<?php

namespace Tests\Feature\Leads;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * O lead carrega o negócio: valor estimado, probabilidade (0–100) e previsão
 * de fechamento. São o que alimenta o forecast e as somas do pipeline.
 */
class CamposDeNegocioTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_cadastra_e_devolve_os_campos_de_negocio(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);

        $this->actingAs($dono)->postJson('/api/leads', [
            'nome' => 'Ana Souza Lima',
            'email' => 'ana@acme.test',
            'valor_estimado' => '1500.50',
            'probabilidade' => 60,
            'previsao_fechamento' => '2026-12-01',
        ])->assertCreated();

        $lead = Usuario::where('email', 'ana@acme.test')->firstOrFail();
        $this->assertSame('1500.50', (string) $lead->valor_estimado);
        $this->assertSame(60, $lead->probabilidade);
        $this->assertSame('2026-12-01', $lead->previsao_fechamento->toDateString());

        $this->actingAs($dono)->getJson("/api/leads/{$lead->id}")
            ->assertOk()
            ->assertJsonPath('probabilidade', 60)
            ->assertJsonPath('previsao_fechamento', '2026-12-01');
    }

    public function test_recusa_probabilidade_fora_de_0_a_100_e_valor_negativo(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);

        $this->actingAs($dono)->postJson('/api/leads', [
            'nome' => 'Ana Souza Lima', 'email' => 'ana@acme.test', 'probabilidade' => 101,
        ])->assertStatus(422);

        $this->actingAs($dono)->postJson('/api/leads', [
            'nome' => 'Ana Souza Lima', 'email' => 'ana@acme.test', 'valor_estimado' => -1,
        ])->assertStatus(422);

        $this->assertDatabaseMissing('usuarios', ['email' => 'ana@acme.test']);
    }

    public function test_edicao_atualiza_os_campos(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono, ['nome' => 'Lead Teste Longo']);

        $this->actingAs($dono)->putJson("/api/leads/{$lead->id}", [
            'nome' => $lead->nome,
            'email' => $lead->email,
            'valor_estimado' => 9000,
            'probabilidade' => 25,
            'previsao_fechamento' => '2027-01-15',
        ])->assertOk();

        $lead->refresh();
        $this->assertSame('9000.00', (string) $lead->valor_estimado);
        $this->assertSame(25, $lead->probabilidade);
        $this->assertSame('2027-01-15', $lead->previsao_fechamento->toDateString());
    }
}
