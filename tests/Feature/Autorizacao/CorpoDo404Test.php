<?php

namespace Tests\Feature\Autorizacao;

use App\Models\Funil;
use App\Models\TarefaPadrao;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Todo 404 de JSON tem o MESMO corpo.
 *
 * O resto do plano de RBAC igualou o STATUS das negativas: policy, binding e
 * tenant respondem 404 igual. O que ficou de fora foi o CORPO, e ele sozinho
 * reabria o buraco inteiro — quatro caminhos, quatro assinaturas:
 *
 *   binding não achou   -> {"message":"No query results for model [App\Models\Usuario] 999999"}
 *   policy negou        -> {"message":"Not Found"}
 *   failedAuthorization -> {"message":""}
 *   catch(\Exception)   -> {"error":"Modelo não encontrado"}
 *
 * A primeira ainda imprime a classe do model e o id pedido com APP_DEBUG=false.
 * Um vendedor separava "lead que existe e é de um colega" de "id que não
 * existe" só lendo a resposta, e enumerava a carteira da equipe inteira.
 *
 * A normalização mora em bootstrap/app.php, em withExceptions().
 */
class CorpoDo404Test extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $dono;
    private User $colega;
    private Usuario $leadDoDono;
    private TarefaPadrao $modeloDoDono;
    private Usuario $leadDeOutroTenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->dono = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->colega = User::factory()->create(['tenant_id' => $this->tenant->id]);

        app(CurrentTenant::class)->set($this->tenant);
        setPermissionsTeamId($this->tenant->id);
        $this->dono->assignRole('vendedor');
        $this->colega->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
        $this->leadDoDono = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->dono->id, 'funil_id' => $funil->id,
        ]);

        $this->modeloDoDono = new TarefaPadrao();
        $this->modeloDoDono->fill(['user_id' => $this->dono->id, 'titulo' => 'Follow-up', 'prazo_dias' => 3]);
        $this->modeloDoDono->tenant_id = $this->tenant->id;
        $this->modeloDoDono->save();

        $outro = Tenant::factory()->create();
        $staffAlheio = User::factory()->create(['tenant_id' => $outro->id]);
        app(CurrentTenant::class)->set($outro);
        $funilAlheio = Funil::factory()->padrao()->create(['tenant_id' => $outro->id]);
        $this->leadDeOutroTenant = Usuario::factory()->create([
            'tenant_id' => $outro->id, 'user_id' => $staffAlheio->id, 'funil_id' => $funilAlheio->id,
        ]);
        app(CurrentTenant::class)->set($this->tenant);
    }

    /**
     * @return array<string, \Illuminate\Testing\TestResponse>
     */
    private function osQuatroCaminhos(): array
    {
        return [
            'binding nao achou' => $this->actingAs($this->colega)
                ->getJson('/api/usuarioPerfil/999999'),

            'policy negou (lead do colega)' => $this->actingAs($this->colega)
                ->getJson("/api/usuarioPerfil/{$this->leadDoDono->id}"),

            'failedAuthorization do FormRequest' => $this->actingAs($this->colega)
                ->putJson("/api/usuarios/{$this->leadDoDono->id}", [
                    'nome' => 'Sequestrado',
                    'email' => $this->leadDoDono->email,
                    'telefone' => '11999998888',
                ]),

            'policy no controller de modelo de tarefa' => $this->actingAs($this->colega)
                ->putJson("/api/tarefa-padroes/{$this->modeloDoDono->id}", [
                    'titulo' => 'Sequestrado', 'prazo_dias' => 1,
                ]),

            'lead de outro tenant' => $this->actingAs($this->colega)
                ->getJson("/api/usuarioPerfil/{$this->leadDeOutroTenant->id}"),
        ];
    }

    public function test_todo_404_de_json_tem_o_mesmo_corpo(): void
    {
        config(['app.debug' => false]);

        foreach ($this->osQuatroCaminhos() as $caminho => $resposta) {
            $this->assertSame(404, $resposta->status(), "{$caminho}: status");
            $this->assertSame(
                '{"message":"Not Found"}',
                $resposta->getContent(),
                "{$caminho}: o corpo do 404 precisa ser identico ao dos outros caminhos",
            );
        }
    }

    /**
     * Com APP_DEBUG=true o corpo continua o mesmo.
     *
     * O callback de render roda ANTES do renderExceptionResponse, que é quem
     * consulta o debug e anexa trace. Se alguém subir um ambiente com debug
     * ligado por engano, o vazamento não volta junto.
     */
    public function test_o_corpo_nao_depende_do_app_debug(): void
    {
        config(['app.debug' => true]);

        foreach ($this->osQuatroCaminhos() as $caminho => $resposta) {
            $this->assertSame('{"message":"Not Found"}', $resposta->getContent(), $caminho);
        }
    }

    /**
     * A prova direta do ataque: as três respostas que o atacante compara não
     * podem diferir em NADA — nem status, nem corpo.
     */
    public function test_colega_inexistente_e_outro_tenant_sao_indistinguiveis(): void
    {
        $respostas = [
            'id do colega' => $this->actingAs($this->colega)->getJson("/api/usuarioPerfil/{$this->leadDoDono->id}"),
            'id inexistente' => $this->actingAs($this->colega)->getJson('/api/usuarioPerfil/999999'),
            'id de outro tenant' => $this->actingAs($this->colega)->getJson("/api/usuarioPerfil/{$this->leadDeOutroTenant->id}"),
        ];

        $assinaturas = array_map(
            fn ($r) => $r->status().' '.$r->getContent(),
            $respostas,
        );

        $this->assertCount(
            1,
            array_unique($assinaturas),
            'as respostas precisam ser identicas; a diferenca enumera os ids: '.json_encode($assinaturas),
        );
    }

    /**
     * O que a normalização NÃO pode capturar: negativa de permissão de CLASSE
     * continua 403, com o corpo que sempre teve. Não há recurso para esconder
     * na criação, e transformar isso em 404 esconderia um erro de configuração
     * de papéis atrás de "não encontrado".
     */
    public function test_403_de_permissao_de_classe_nao_e_afetado(): void
    {
        $semPapel = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($semPapel)
            ->postJson('/api/usuarios', [
                'nome' => 'Fulano de Tal', 'email' => 'fulano@example.com', 'telefone' => '11999998888',
            ])
            ->assertForbidden()
            ->assertJson(['message' => 'This action is unauthorized.']);
    }

    /**
     * E o outro lado do `expectsJson()`: a rota web do Inertia continua
     * devolvendo HTML no 404, e não um JSON que o frontend não sabe renderizar.
     */
    public function test_a_rota_html_do_inertia_continua_respondendo_html(): void
    {
        $resposta = $this->actingAs($this->colega)->get('/leads/999999');

        $resposta->assertNotFound();
        $this->assertStringContainsString('text/html', (string) $resposta->headers->get('content-type'));
        $this->assertStringNotContainsString('{"message":"Not Found"}', $resposta->getContent());
    }
}
