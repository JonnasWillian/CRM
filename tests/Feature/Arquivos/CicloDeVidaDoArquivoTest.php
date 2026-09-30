<?php

namespace Tests\Feature\Arquivos;

use App\Models\arquivo;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class CicloDeVidaDoArquivoTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;
    private User $dono;
    private Usuario $lead;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->tenant = $this->novoTenant();
        $this->dono = $this->agente($this->tenant);
        $this->lead = $this->lead($this->tenant, $this->dono);
    }

    private function enviar(): arquivo
    {
        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'arquivo' => UploadedFile::fake()->create('rg.pdf', 10),
        ])->assertSuccessful();

        return arquivo::latest('id')->firstOrFail();
    }

    public function test_lixeira_mantem_o_arquivo_para_poder_restaurar(): void
    {
        $arquivo = $this->enviar();
        $arquivo->delete();

        Storage::disk('local')->assertExists($arquivo->local);
    }

    public function test_exclusao_definitiva_remove_o_arquivo_do_disco(): void
    {
        $arquivo = $this->enviar();
        $arquivo->forceDelete();

        Storage::disk('local')->assertMissing($arquivo->local);
    }

    /** Linha antiga, do jeito que o sistema gravava até a Tarefa 2. */
    private function anexoLegado(string $local, bool $comArquivo = true): arquivo
    {
        if ($comArquivo) {
            Storage::disk('public')->put($local, 'conteudo-legado');
        }

        return arquivo::create(['nome' => basename($local), 'local' => $local, 'usuario_id' => $this->lead->id]);
    }

    public function test_dry_run_so_relata(): void
    {
        $legado = $this->anexoLegado('arquivos/antigo.pdf');

        $this->artisan('arquivos:migrar-para-privado', ['--dry-run' => true])
            ->expectsOutputToContain('arquivos/antigo.pdf')
            ->assertSuccessful();

        $this->assertSame('arquivos/antigo.pdf', $legado->fresh()->local);
        Storage::disk('public')->assertExists('arquivos/antigo.pdf');
    }

    public function test_migra_para_a_pasta_do_lead_e_preenche_metadados(): void
    {
        $legado = $this->anexoLegado('arquivos/antigo.pdf');

        $this->artisan('arquivos:migrar-para-privado')->assertSuccessful();

        $novo = $legado->fresh();
        $this->assertStringStartsWith("tenants/{$this->tenant->id}/leads/{$this->lead->id}/", $novo->local);
        Storage::disk('local')->assertExists($novo->local);
        Storage::disk('public')->assertMissing('arquivos/antigo.pdf');
        $this->assertSame(strlen('conteudo-legado'), (int) $novo->tamanho);
    }

    public function test_rodar_duas_vezes_nao_muda_nada_na_segunda(): void
    {
        $legado = $this->anexoLegado('arquivos/antigo.pdf');

        $this->artisan('arquivos:migrar-para-privado')->assertSuccessful();
        $depoisDaPrimeira = $legado->fresh()->local;

        $this->artisan('arquivos:migrar-para-privado')->assertSuccessful();
        $this->assertSame($depoisDaPrimeira, $legado->fresh()->local);
    }

    public function test_linha_sem_arquivo_e_relatada_e_nao_alterada(): void
    {
        $semArquivo = $this->anexoLegado('arquivos/sumiu.pdf', comArquivo: false);

        $this->artisan('arquivos:migrar-para-privado')
            ->expectsOutputToContain('arquivos/sumiu.pdf')
            ->assertSuccessful();

        $this->assertSame('arquivos/sumiu.pdf', $semArquivo->fresh()->local);
    }

    public function test_orfao_no_disco_so_e_apagado_com_a_opcao_explicita(): void
    {
        Storage::disk('public')->put('arquivos/orfao.pdf', 'x');

        $this->artisan('arquivos:migrar-para-privado')
            ->expectsOutputToContain('arquivos/orfao.pdf')
            ->assertSuccessful();
        Storage::disk('public')->assertExists('arquivos/orfao.pdf');

        $this->artisan('arquivos:migrar-para-privado', ['--limpar-orfaos' => true])->assertSuccessful();
        Storage::disk('public')->assertMissing('arquivos/orfao.pdf');
    }
}
