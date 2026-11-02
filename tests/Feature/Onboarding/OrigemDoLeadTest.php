<?php

namespace Tests\Feature\Onboarding;

use App\Jobs\ImportarLeads;
use App\Models\Funil;
use App\Models\PerfilDeEmpresa;
use App\Models\Usuario;
use App\Services\Importacao\CamposDeImportacao;
use App\Services\TenantBootstrapper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Campo Origem do lead (opções vindas do questionário) e o rótulo do nome
 * conforme "Para quem você vende": no cadastro, na grade, na exportação e
 * na importação.
 */
class OrigemDoLeadTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_lead_grava_e_devolve_origem(): void
    {
        $t = $this->novoTenant();
        $dono = $this->agente($t);

        $this->actingAs($dono)->postJson('/api/leads', ['nome' => 'Ana Souza Lima', 'email' => 'ana@acme.test', 'origem' => 'Indicação'])->assertCreated();

        $lead = Usuario::where('email', 'ana@acme.test')->firstOrFail();
        $this->assertSame('Indicação', $lead->origem);
        $this->actingAs($dono)->getJson("/api/leads/{$lead->id}")->assertOk()->assertJsonPath('origem', 'Indicação');
    }

    public function test_origem_acima_de_60_e_recusada(): void
    {
        $dono = $this->agente($this->novoTenant());

        $this->actingAs($dono)->postJson('/api/leads', ['nome' => 'Ana Souza Lima', 'email' => 'ana@acme.test', 'origem' => str_repeat('a', 61)])
            ->assertUnprocessable()->assertJsonValidationErrors('origem');
    }

    public function test_exportacao_traz_origem(): void
    {
        $t = $this->novoTenant();
        $dono = $this->agente($t);
        $this->lead($t, $dono, ['nome' => 'Alfa Comercial', 'origem' => 'Site']);

        $csv = $this->actingAs($dono)->get('/api/leads/exportar')->streamedContent();
        $linhas = array_map(fn ($l) => str_getcsv($l, ';'), array_values(array_filter(explode("\n", substr($csv, 3)))));

        $coluna = array_search('origem', $linhas[0], true);
        $this->assertSame(9, $coluna); // logo depois de previsao_fechamento
        $this->assertSame('Site', $linhas[1][$coluna]);
    }

    public function test_importacao_mapeia_coluna_origem(): void
    {
        $this->assertSame(2, CamposDeImportacao::sugerir(['Nome', 'E-mail', 'Origem'])['origem'] ?? null);

        $t = $this->novoTenant();
        $gestor = $this->agente($t, 'gestor');
        Funil::factory()->padrao()->create(['tenant_id' => $t->id]);
        $arquivo = UploadedFile::fake()->createWithContent('leads.csv', "nome;email;origem\nCliente Novo;novo@ex.test;Indicação\n");

        $id = $this->actingAs($gestor)->postJson('/api/importacoes', ['arquivo' => $arquivo])->json('data.id');
        Queue::fake();
        $this->actingAs($gestor)->postJson("/api/importacoes/{$id}/iniciar", ['mapeamento' => ['nome' => 0, 'email' => 1, 'origem' => 2], 'opcoes' => ['duplicados' => 'ignorar']])->assertOk();
        (new ImportarLeads($id))->handle();

        $this->assertSame('Indicação', Usuario::where('email', 'novo@ex.test')->value('origem'));
    }

    public function test_props_da_empresa_seguem_o_perfil(): void
    {
        $t = $this->novoTenant();
        TenantBootstrapper::bootstrap($t);
        $admin = $this->agente($t, 'admin');
        $perfil = PerfilDeEmpresa::create(['tenant_id' => $t->id]);
        $perfil->forceFill(['respondido_em' => now(), 'respostas' => ['para_quem' => 'empresas', 'origens' => ['indicacao'], 'tamanho' => '1']])->save();

        $this->actingAs($admin)->get('/leads')->assertInertia(fn (Assert $p) => $p
            ->where('empresa.rotulo_nome_lead', 'Nome da empresa')
            ->where('empresa.origens', ['Indicação', 'Outro'])
            ->where('empresa.equipe_maior_que_um', false));
    }
}
