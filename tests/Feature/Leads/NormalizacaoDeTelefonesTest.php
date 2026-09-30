<?php

namespace Tests\Feature\Leads;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class NormalizacaoDeTelefonesTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private int $bom;
    private int $ruim;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);

        // Direto na tabela: é o formato que as linhas antigas têm depois de a
        // coluna virar texto (só dígitos, sem "+").
        $this->bom = $this->lead($tenant, $dono)->id;
        $this->ruim = $this->lead($tenant, $dono)->id;
        DB::table('usuarios')->where('id', $this->bom)->update(['telefone' => '11999998888']);
        DB::table('usuarios')->where('id', $this->ruim)->update(['telefone' => '123']);
    }

    public function test_dry_run_relata_e_nao_altera(): void
    {
        $this->artisan('telefones:normalizar', ['--dry-run' => true])
            ->expectsOutputToContain('123')
            ->assertSuccessful();

        $this->assertSame('11999998888', DB::table('usuarios')->where('id', $this->bom)->value('telefone'));
    }

    public function test_normaliza_o_que_reconhece_e_preserva_o_resto(): void
    {
        $this->artisan('telefones:normalizar')->assertSuccessful();

        $this->assertSame('+5511999998888', DB::table('usuarios')->where('id', $this->bom)->value('telefone'));
        $this->assertSame('123', DB::table('usuarios')->where('id', $this->ruim)->value('telefone'));
    }

    public function test_e_idempotente(): void
    {
        $this->artisan('telefones:normalizar')->assertSuccessful();
        $this->artisan('telefones:normalizar')->expectsOutputToContain('0 telefone(s) alterado(s)')->assertSuccessful();
    }
}
