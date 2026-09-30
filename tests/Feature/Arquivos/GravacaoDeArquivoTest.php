<?php

namespace Tests\Feature\Arquivos;

use App\Models\arquivo;
use App\Models\Projeto;
use App\Models\ProjetoAnexo;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * P1 (gravação): o arquivo vai para o disco PRIVADO, num caminho que diz de
 * que empresa e de que lead/projeto ele é, e nunca fica no disco sem linha no
 * banco.
 */
class GravacaoDeArquivoTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;
    private User $dono;
    private Usuario $lead;
    private Projeto $projeto;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->tenant = $this->novoTenant();
        $this->dono = $this->agente($this->tenant);
        $this->lead = $this->lead($this->tenant, $this->dono);
        $this->projeto = $this->projeto($this->lead);
    }

    public function test_anexo_de_lead_vai_para_o_disco_privado_na_pasta_do_lead(): void
    {
        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'nome' => 'RG',
            'arquivo' => UploadedFile::fake()->create('rg.pdf', 30, 'application/pdf'),
        ])->assertSuccessful();

        $linha = arquivo::firstOrFail();

        $this->assertStringStartsWith("tenants/{$this->tenant->id}/leads/{$this->lead->id}/", $linha->local);
        $this->assertStringEndsWith('.pdf', $linha->local);
        Storage::disk('local')->assertExists($linha->local);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(30 * 1024, (int) $linha->tamanho);
        $this->assertSame('application/pdf', $linha->mime);
    }

    public function test_anexo_de_projeto_vai_para_a_pasta_do_projeto(): void
    {
        $this->actingAs($this->dono)->postJson('/api/projetoAnexo', [
            'usuario_id' => $this->projeto->id,
            'arquivo' => UploadedFile::fake()->create('contrato.pdf', 10, 'application/pdf'),
        ])->assertSuccessful();

        $linha = ProjetoAnexo::firstOrFail();

        $this->assertStringStartsWith("tenants/{$this->tenant->id}/projetos/{$this->projeto->id}/", $linha->local);
        Storage::disk('local')->assertExists($linha->local);
    }

    public function test_extensao_gravada_vem_do_conteudo_e_nao_do_nome(): void
    {
        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'arquivo' => UploadedFile::fake()->create('foto.pdf', 10, 'image/png'),
        ])->assertSuccessful();

        $this->assertStringEndsWith('.png', arquivo::firstOrFail()->local);
    }

    public function test_falha_ao_gravar_a_linha_nao_deixa_arquivo_no_disco(): void
    {
        arquivo::creating(fn () => throw new \RuntimeException('falha simulada'));

        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'arquivo' => UploadedFile::fake()->create('rg.pdf', 10),
        ])->assertServerError();

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_falha_ao_gravar_o_anexo_de_projeto_nao_deixa_arquivo_no_disco(): void
    {
        ProjetoAnexo::creating(fn () => throw new \RuntimeException('falha simulada'));

        $this->actingAs($this->dono)->postJson('/api/projetoAnexo', [
            'usuario_id' => $this->projeto->id,
            'arquivo' => UploadedFile::fake()->create('c.pdf', 10),
        ])->assertServerError();

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    /**
     * M4: falha DEPOIS do INSERT (ex.: o observer que grava o activity log)
     * não pode deixar linha apontando para um arquivo que já saiu do disco.
     */
    public function test_falha_depois_do_insert_desfaz_a_linha_e_o_arquivo(): void
    {
        arquivo::created(fn () => throw new \RuntimeException('falha simulada no observer'));

        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'arquivo' => UploadedFile::fake()->create('rg.pdf', 10),
        ])->assertServerError();

        $this->assertSame(0, arquivo::withTrashed()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    /**
     * `assertStringNotContainsString('tenants/', ...)` no JSON cru passava
     * por acidente: json_encode escapa '/' como '\/', então a substring
     * literal 'tenants/' nunca aparece ali mesmo que o campo `local` esteja
     * no payload. `assertJsonMissingPath` decodifica o JSON antes de olhar,
     * então é o campo que precisa estar ausente — não a grafia da barra.
     */
    public function test_resposta_do_upload_de_lead_nao_expoe_o_caminho_interno(): void
    {
        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'arquivo' => UploadedFile::fake()->create('rg.pdf', 10),
        ])->assertSuccessful()->assertJsonMissingPath('data.local');
    }

    public function test_resposta_do_upload_de_projeto_nao_expoe_o_caminho_interno(): void
    {
        $this->actingAs($this->dono)->postJson('/api/projetoAnexo', [
            'usuario_id' => $this->projeto->id,
            'arquivo' => UploadedFile::fake()->create('c.pdf', 10),
        ])->assertSuccessful()->assertJsonMissingPath('data.local');
    }
}
