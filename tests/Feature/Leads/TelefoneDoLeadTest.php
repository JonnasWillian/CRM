<?php

namespace Tests\Feature\Leads;

use App\Models\User;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * P5: o servidor normaliza qualquer forma de telefone — com máscara, sem
 * máscara, do quick-add do Kanban que não tem máscara nenhuma — e guarda
 * E.164. O front deixa de ser responsável por isso.
 */
class TelefoneDoLeadTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private User $dono;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = $this->novoTenant();
        $this->dono = $this->agente($tenant);
    }

    private function criar(array $dados)
    {
        return $this->actingAs($this->dono)->postJson('/api/usuarios', array_merge([
            'nome' => 'Cliente Teste',
            'email' => 'cliente'.uniqid().'@exemplo.com',
        ], $dados));
    }

    public function test_telefone_com_mascara_e_gravado_em_e164(): void
    {
        $this->criar(['telefone' => '(11) 99999-8888'])->assertCreated();

        $this->assertDatabaseHas('usuarios', ['telefone' => '+5511999998888']);
    }

    public function test_fixo_de_10_digitos_e_aceito(): void
    {
        $this->criar(['telefone' => '1133334444'])->assertCreated();

        $this->assertDatabaseHas('usuarios', ['telefone' => '+551133334444']);
    }

    /** Review Focus 3 */
    public function test_telefone_e_opcional(): void
    {
        $this->criar([])->assertCreated();
        $this->criar(['telefone' => ''])->assertCreated();

        $this->assertSame(2, Usuario::whereNull('telefone')->count());
    }

    public function test_telefone_invalido_e_recusado_com_mensagem(): void
    {
        $this->criar(['telefone' => '123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['telefone' => 'com DDD']);
    }

    public function test_telefone_como_array_e_recusado_sem_erro_500(): void
    {
        $this->criar(['telefone' => ['11999998888']])->assertUnprocessable();
    }

    public function test_edicao_usa_a_mesma_regra(): void
    {
        $lead = $this->lead(app(\App\Support\Tenancy\CurrentTenant::class)->get(), $this->dono);

        $this->actingAs($this->dono)->putJson("/api/usuarios/{$lead->id}", [
            'nome' => 'Cliente Editado',
            'email' => $lead->email,
            'telefone' => '(21) 3333-4444',
        ])->assertOk();

        $this->assertSame('+552133334444', $lead->fresh()->telefone);
    }
}
