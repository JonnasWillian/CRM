<?php

namespace Tests\Feature\Importacao;

use App\Jobs\ImportarLeads;
use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Importacao;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/** Importar CSV: prévia (separador, codificação, mapeamento sugerido) e o job (validação por linha, duplicados, erros). */
class ImportacaoDeLeadsTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function csv(string $conteudo, string $nome = 'leads.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nome, $conteudo);
    }

    public function test_previa_detecta_separador_codificacao_e_sugere_mapeamento(): void
    {
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $conteudo = mb_convert_encoding("Nome,E-mail,Telefone,Observação\nJoão Ávila,joao@ex.test,11999998888,Cliente antigo\nMaria,maria@ex.test,,\n", 'Windows-1252', 'UTF-8');

        $dados = $this->actingAs($gestor)->postJson('/api/importacoes', ['arquivo' => $this->csv($conteudo)])->assertCreated()->json('data');

        $this->assertSame(',', $dados['delimitador']);
        $this->assertSame('Windows-1252', $dados['codificacao']);
        $this->assertSame(['Nome', 'E-mail', 'Telefone', 'Observação'], $dados['cabecalhos']);
        $this->assertSame('João Ávila', $dados['amostra'][0][0]);
        $this->assertSame(2, $dados['total_linhas']);
        $this->assertSame(['nome' => 0, 'email' => 1, 'telefone' => 2, 'descricao' => 3], $dados['mapeamento']);
    }

    public function test_vendedor_nao_importa(): void
    {
        $tenant = $this->novoTenant();
        $vendedor = $this->agente($tenant);
        $this->actingAs($vendedor)->postJson('/api/importacoes', ['arquivo' => $this->csv("nome;email\nA;a@ex.test\n")])->assertForbidden();
    }

    public function test_iniciar_exige_nome_e_email_mapeados_e_enfileira(): void
    {
        Queue::fake();
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $id = $this->actingAs($gestor)->postJson('/api/importacoes', ['arquivo' => $this->csv("nome;email\nA;a@ex.test\n")])->json('data.id');

        $this->actingAs($gestor)->postJson("/api/importacoes/{$id}/iniciar", ['mapeamento' => ['nome' => 0], 'opcoes' => ['duplicados' => 'ignorar']])
            ->assertUnprocessable()->assertJsonValidationErrors('mapeamento.email');

        $this->actingAs($gestor)->postJson("/api/importacoes/{$id}/iniciar", ['mapeamento' => ['nome' => 0, 'email' => 1], 'opcoes' => ['duplicados' => 'ignorar']])
            ->assertOk()->assertJsonPath('data.status', 'na_fila');
        Queue::assertPushed(ImportarLeads::class);
    }

    public function test_job_cria_atualiza_ignora_e_registra_erros_por_linha(): void
    {
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        $ana = $this->agente($tenant);
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $primeiro = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 1, 'descricao' => 'Novo']);
        $negociacao = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'ordem' => 2, 'descricao' => 'Negociação']);
        $existente = $this->lead($tenant, $gestor, ['email' => 'existe@ex.test', 'nome' => 'Nome Antigo', 'telefone' => '+5511988887777']);

        $csv = "nome;email;telefone;estagio;valor_estimado;responsavel\n"
            ."Cliente Novo;novo@ex.test;(11) 97777-6666;Negociação;1.500,00;{$ana->email}\n"
            ."Nome Atualizado;existe@ex.test;;;;\n"
            ."Sem Email;;;;;\n"
            ."Repetido Um;rep@ex.test;;;;\n"
            ."Repetido Dois;rep@ex.test;;;;\n"
            ."Fone Ruim;fone@ex.test;123;EstagioQueNaoExiste;;ninguem@ex.test\n";

        $id = $this->actingAs($gestor)->postJson('/api/importacoes', ['arquivo' => $this->csv($csv)])->json('data.id');
        Queue::fake();
        $this->actingAs($gestor)->postJson("/api/importacoes/{$id}/iniciar", [
            'mapeamento' => ['nome' => 0, 'email' => 1, 'telefone' => 2, 'estagio' => 3, 'valor_estimado' => 4, 'responsavel' => 5],
            'opcoes' => ['duplicados' => 'atualizar'],
        ])->assertOk();

        (new ImportarLeads($id))->handle();

        $imp = Importacao::find($id);
        $this->assertSame(Importacao::CONCLUIDA, $imp->status);
        $this->assertSame(3, $imp->criadas);      // Cliente Novo, Repetido Um, Fone Ruim
        $this->assertSame(1, $imp->atualizadas);  // existe@
        $this->assertSame(2, $imp->com_erro);     // sem e-mail, e-mail repetido no arquivo
        // Linha 1 é o cabeçalho. Erros barram a linha; avisos não (a linha entra com o padrão).
        $this->assertSame([4, 6], collect($imp->erros)->where('aviso', false)->pluck('linha')->values()->all());
        $this->assertSame([7], collect($imp->erros)->where('aviso', true)->pluck('linha')->unique()->values()->all());

        $novo = Usuario::firstWhere('email', 'novo@ex.test');
        $this->assertSame($negociacao->id, $novo->estagio_id);
        $this->assertSame($ana->id, $novo->user_id);
        $this->assertSame('1500.00', $novo->valor_estimado);
        $this->assertSame('+5511977776666', $novo->telefone);

        // Atualizar não apaga campo vazio.
        $existente->refresh();
        $this->assertSame('Nome Atualizado', $existente->nome);
        $this->assertSame('+5511988887777', $existente->telefone);

        $ruim = Usuario::firstWhere('email', 'fone@ex.test');
        $this->assertNull($ruim->telefone);
        $this->assertSame($primeiro->id, $ruim->estagio_id);
        $this->assertSame($gestor->id, $ruim->user_id);

        $erros = $this->actingAs($gestor)->get("/api/importacoes/{$id}/erros");
        $erros->assertOk();
        $this->assertStringContainsString('Sem Email', $erros->streamedContent());
    }

    public function test_ignorar_duplicados_nao_mexe_no_existente_e_outro_tenant_nao_ve(): void
    {
        $tenant = $this->novoTenant();
        $gestor = $this->agente($tenant, 'gestor');
        Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $existente = $this->lead($tenant, $gestor, ['email' => 'existe@ex.test', 'nome' => 'Nome Antigo']);

        $id = $this->actingAs($gestor)->postJson('/api/importacoes', ['arquivo' => $this->csv("nome;email\nNome Novo;existe@ex.test\n")])->json('data.id');
        Queue::fake();
        $this->actingAs($gestor)->postJson("/api/importacoes/{$id}/iniciar", ['mapeamento' => ['nome' => 0, 'email' => 1], 'opcoes' => ['duplicados' => 'ignorar']]);
        (new ImportarLeads($id))->handle();

        $this->assertSame('Nome Antigo', $existente->fresh()->nome);
        $this->assertSame(1, Importacao::find($id)->ignoradas);

        $outro = $this->novoTenant();
        $alheio = $this->agente($outro, 'gestor');
        $this->actingAs($alheio)->getJson("/api/importacoes/{$id}")->assertNotFound();
    }
}
