<?php

namespace Tests\Feature\Arquivos;

use App\Models\arquivo;
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
 * P1 (leitura): o conteúdo de um anexo só sai por uma rota que autentica e
 * pergunta à policy — a mesma regra de quem pode ver o lead.
 */
class DownloadProtegidoTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;
    private User $dono;
    private User $intruso;
    private User $gestor;
    private Usuario $lead;
    private arquivo $arquivo;
    private ProjetoAnexo $anexo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->tenant = $this->novoTenant();
        $this->dono = $this->agente($this->tenant);
        $this->intruso = $this->agente($this->tenant);
        $this->gestor = $this->agente($this->tenant, 'gestor');
        $this->lead = $this->lead($this->tenant, $this->dono);
        $projeto = $this->projeto($this->lead);

        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'nome' => 'rg.pdf',
            'arquivo' => UploadedFile::fake()->create('rg.pdf', 10),
        ])->assertSuccessful();
        $this->arquivo = arquivo::firstOrFail();

        $this->actingAs($this->dono)->postJson('/api/projetoAnexo', [
            'usuario_id' => $projeto->id,
            'nome' => 'contrato.pdf',
            'arquivo' => UploadedFile::fake()->create('contrato.pdf', 10),
        ])->assertSuccessful();
        $this->anexo = ProjetoAnexo::firstOrFail();
    }

    public function test_dono_baixa_como_anexo_e_com_nosniff(): void
    {
        $this->actingAs($this->dono)
            ->get(route('arquivos.download', $this->arquivo))
            ->assertOk()
            ->assertDownload('rg.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_gestor_baixa_arquivo_da_equipe(): void
    {
        $this->actingAs($this->gestor)->get(route('arquivos.download', $this->arquivo))->assertOk();
    }

    public function test_colega_sem_view_all_recebe_404(): void
    {
        $this->actingAs($this->intruso)->get(route('arquivos.download', $this->arquivo))->assertNotFound();
    }

    public function test_usuario_de_outro_tenant_recebe_404(): void
    {
        $outro = $this->novoTenant();
        $estranho = $this->agente($outro, 'admin');

        $this->actingAs($estranho)->get(route('arquivos.download', $this->arquivo))->assertNotFound();
    }

    public function test_visitante_vai_para_o_login(): void
    {
        auth()->logout();

        $this->get(route('arquivos.download', $this->arquivo))->assertRedirect(route('login'));
    }

    public function test_arquivo_na_lixeira_nao_e_servido(): void
    {
        $this->arquivo->delete();

        $this->actingAs($this->dono)->get("/arquivos/{$this->arquivo->id}/download")->assertNotFound();
    }

    /** Review Focus 5 */
    public function test_nome_vazio_ainda_gera_nome_de_download_valido(): void
    {
        $this->arquivo->update(['nome' => '']);

        $this->actingAs($this->dono)
            ->get(route('arquivos.download', $this->arquivo))
            ->assertOk()
            ->assertDownload(basename($this->arquivo->local));
    }

    /**
     * Barra no nome quebrava o Content-Disposition (o Symfony lança
     * InvalidArgumentException e o usuário via 500).
     */
    public function test_nome_com_barra_ainda_baixa_sem_barra_no_nome(): void
    {
        $this->arquivo->update(['nome' => 'Contrato 01/2026']);

        $resposta = $this->actingAs($this->dono)->get(route('arquivos.download', $this->arquivo));

        $resposta->assertOk();
        $disposicao = $resposta->headers->get('Content-Disposition');
        $this->assertStringNotContainsString('/', $disposicao);
        $this->assertStringContainsString('Contrato 01-2026', $disposicao);
    }

    public function test_nome_sem_extensao_ganha_a_extensao_do_arquivo_guardado(): void
    {
        $this->arquivo->update(['nome' => 'Contrato']);
        $extensao = pathinfo($this->arquivo->local, PATHINFO_EXTENSION);

        $this->actingAs($this->dono)
            ->get(route('arquivos.download', $this->arquivo))
            ->assertOk()
            ->assertDownload("Contrato.{$extensao}");

        $this->assertSame('pdf', $extensao);
    }

    public function test_anexo_de_projeto_de_outro_tenant_recebe_404(): void
    {
        $estranho = $this->agente($this->novoTenant(), 'admin');

        $this->actingAs($estranho)
            ->get(route('projetoAnexos.download', $this->anexo))
            ->assertNotFound();
    }

    public function test_listagem_nao_expoe_caminho_e_traz_url_de_download(): void
    {
        $this->actingAs($this->dono)
            ->getJson("/api/arquivos?usuario_id={$this->lead->id}")
            ->assertOk()
            ->assertJsonMissingPath('0.local')
            ->assertJsonPath('0.url_download', route('arquivos.download', $this->arquivo));
    }

    public function test_anexo_de_projeto_segue_as_mesmas_regras(): void
    {
        $url = route('projetoAnexos.download', $this->anexo);

        $this->actingAs($this->dono)->get($url)->assertOk()->assertDownload('contrato.pdf');
        $this->actingAs($this->gestor)->get($url)->assertOk();
        $this->actingAs($this->intruso)->get($url)->assertNotFound();

        $this->anexo->delete();
        $this->actingAs($this->dono)->get($url)->assertNotFound();
    }
}
