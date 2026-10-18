<?php

namespace Tests\Feature\Api;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\MotivoPerda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * Todo 422 da API tem o formato do framework: `message` + `errors`
 * (validação) ou só `message` (regra de domínio). O front lê `message`
 * para o toast e `errors` para o campo; nada de `erros` nem `error`.
 */
class FormatoDe422Test extends TestCase
{
    use CenarioDeTenant, RefreshDatabase;

    public function test_validacao_vem_como_message_e_errors(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);

        foreach ([
            ['post', '/api/leads', ['nome' => 'A']],
            ['post', '/api/funis', ['nome' => '']],
            ['post', "/api/funis/{$funil->id}/estagios", ['descricao' => '', 'tipo' => 'x']],
            ['post', '/api/motivos-perda', ['descricao' => '']],
            ['post', '/api/tarefa-padroes', ['titulo' => '']],
        ] as [$verbo, $uri, $corpo]) {
            $json = $this->actingAs($admin)->{$verbo.'Json'}($uri, $corpo)->assertUnprocessable()->json();
            $this->assertArrayHasKey('message', $json, $uri);
            $this->assertArrayHasKey('errors', $json, $uri);
            $this->assertArrayNotHasKey('erros', $json, $uri);
            $this->assertArrayNotHasKey('error', $json, $uri);
        }
    }

    public function test_regra_de_dominio_vem_como_message(): void
    {
        $tenant = $this->novoTenant();
        $admin = $this->agente($tenant, 'admin');
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $unico = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id]);
        $motivo = MotivoPerda::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($admin)->deleteJson("/api/estagios/{$unico->id}")->assertUnprocessable()
            ->assertJsonStructure(['message'])->assertJsonMissingPath('error');
        $this->actingAs($admin)->deleteJson("/api/motivos-perda/{$motivo->id}")->assertUnprocessable()
            ->assertJsonStructure(['message'])->assertJsonMissingPath('error');
    }
}
