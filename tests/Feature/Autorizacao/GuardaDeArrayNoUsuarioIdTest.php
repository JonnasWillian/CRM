<?php

namespace Tests\Feature\Autorizacao;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * O id do dono deixou de vir no CORPO (onde um array virava `findMany()` e
 * furava a guarda `$model !== null`): ele vem da URL, e `Route::pattern`
 * exige inteiro. Qualquer outra coisa não casa rota nenhuma — 404 antes de
 * binding, validação ou policy. Um `usuario_id` enviado no corpo é ignorado.
 */
class GuardaDeArrayNoUsuarioIdTest extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_id_nao_numerico_na_rota_nao_casa_nenhuma_rota(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);

        $this->actingAs($dono)->postJson('/api/leads/abc/arquivos', ['arquivo' => UploadedFile::fake()->create('a.pdf', 10)])->assertNotFound();
        $this->actingAs($dono)->postJson('/api/projetos/abc/anexos', ['arquivo' => UploadedFile::fake()->create('a.pdf', 10)])->assertNotFound();
        $this->actingAs($dono)->postJson('/api/leads/abc/projetos', ['nome' => 'Proposta'])->assertNotFound();
        $this->actingAs($dono)->postJson('/api/leads/1[]/anotacoes', ['descricao' => 'x'])->assertNotFound();
    }

    public function test_usuario_id_no_corpo_e_ignorado_a_favor_da_rota(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $meu = $this->lead($tenant, $dono);
        $doColega = $this->lead($tenant, $this->agente($tenant));

        $this->actingAs($dono)->postJson("/api/leads/{$meu->id}/anotacoes", ['descricao' => 'Minha', 'usuario_id' => [$doColega->id]])->assertCreated();

        $this->assertDatabaseHas('anotacoes', ['usuario_id' => $meu->id, 'descricao' => 'Minha']);
        $this->assertDatabaseMissing('anotacoes', ['usuario_id' => $doColega->id]);
    }
}
