<?php

namespace Tests\Feature\Arquivos;

use App\Models\Projeto;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Arquivos\PoliticaDeUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * P2: o que entra no sistema como arquivo. A lista é de permitidos, não de
 * proibidos — um tipo novo e perigoso não pode passar só porque ninguém
 * lembrou de proibi-lo.
 */
class PoliticaDeUploadTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private User $dono;
    private Usuario $lead;
    private Projeto $projeto;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $tenant = $this->novoTenant();
        $this->dono = $this->agente($tenant);
        $this->lead = $this->lead($tenant, $this->dono);
        $this->projeto = $this->projeto($this->lead);
    }

    public static function recusados(): array
    {
        return [
            'html (XSS no mesmo domínio)' => ['pagina.html', 'text/html', 1],
            'svg (script embutido)' => ['logo.svg', 'image/svg+xml', 1],
            'executável com nome de pdf' => ['contrato.pdf', 'application/x-msdownload', 1],
            'acima do limite' => ['grande.pdf', 'application/pdf', PoliticaDeUpload::MAX_KB + 1],
        ];
    }

    public static function aceitos(): array
    {
        return [
            'pdf' => ['proposta.pdf', 'application/pdf'],
            'png' => ['foto.png', 'image/png'],
            'xlsx' => ['custos.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'csv' => ['lista.csv', 'text/csv'],
        ];
    }

    #[DataProvider('recusados')]
    public function test_anexo_de_lead_recusa(string $nome, string $mime, int $kb): void
    {
        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'arquivo' => UploadedFile::fake()->create($nome, $kb, $mime),
        ])->assertUnprocessable()->assertJsonValidationErrors('arquivo');

        $this->assertDatabaseCount('arquivos', 0);
    }

    #[DataProvider('recusados')]
    public function test_anexo_de_projeto_recusa(string $nome, string $mime, int $kb): void
    {
        $this->actingAs($this->dono)->postJson('/api/projetoAnexo', [
            'usuario_id' => $this->projeto->id,
            'arquivo' => UploadedFile::fake()->create($nome, $kb, $mime),
        ])->assertUnprocessable()->assertJsonValidationErrors('arquivo');

        $this->assertDatabaseCount('projetoAnexos', 0);
    }

    #[DataProvider('aceitos')]
    public function test_anexo_de_lead_aceita(string $nome, string $mime): void
    {
        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'arquivo' => UploadedFile::fake()->create($nome, 20, $mime),
        ])->assertSuccessful();

        $this->assertDatabaseCount('arquivos', 1);
    }

    #[DataProvider('aceitos')]
    public function test_anexo_de_projeto_aceita(string $nome, string $mime): void
    {
        $this->actingAs($this->dono)->postJson('/api/projetoAnexo', [
            'usuario_id' => $this->projeto->id,
            'arquivo' => UploadedFile::fake()->create($nome, 20, $mime),
        ])->assertSuccessful();

        $this->assertDatabaseCount('projetoAnexos', 1);
    }

    public function test_nome_com_mais_de_255_caracteres_e_recusado(): void
    {
        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'nome' => str_repeat('a', 256),
            'arquivo' => UploadedFile::fake()->create('a.pdf', 10),
        ])->assertUnprocessable()->assertJsonValidationErrors('nome');
    }

    /** Review Focus 1: o PHP descartou o upload antes de a validação ver o arquivo. */
    public function test_upload_barrado_pelo_php_explica_o_limite(): void
    {
        $caminho = tempnam(sys_get_temp_dir(), 'up');
        $barrado = new UploadedFile($caminho, 'grande.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true);

        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'arquivo' => $barrado,
        ])->assertUnprocessable()->assertJsonValidationErrors(['arquivo' => 'limite de upload do servidor']);
    }
}
