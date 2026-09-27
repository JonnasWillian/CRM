<?php

namespace Tests\Feature\Autorizacao;

use App\Models\Anotacao;
use App\Models\arquivo;
use App\Models\Funil;
use App\Models\Projeto;
use App\Models\ProjetoAnexo;
use App\Models\ProjetoAnotacao;
use App\Models\Statu;
use App\Models\Tarefa;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Item 9 da rodada B: `leads.view-all` (o papel gestor) precisa alcançar a
 * equipe inteira em toda policy que reconhece esse atalho — sete no total
 * (`grep -rl "leads.view-all" app/Policies`): UsuarioPolicy, AnotacaoPolicy,
 * ArquivoPolicy, ProjetoPolicy, ProjetoAnotacaoPolicy, ProjetoAnexoPolicy e
 * TarefaPolicy.
 *
 * UsuarioPolicy já tem essa cobertura —
 * UrlDoLeadTest::test_gestor_abre_a_tela_de_lead_de_outro_agente.
 * TarefaPadraoPolicy documenta e escolhe a EXCLUSÃO deliberada do atalho
 * (modelo de tarefa é ferramenta pessoal do agente, não dado de carteira) —
 * não faz parte deste grupo e não deveria ganhar este teste.
 *
 * Faltam as outras seis. Cada uma delega para um `doDono()` PRIVADO com
 * exatamente duas saídas — `can('leads.view-all')` ou dono direto — e os
 * métodos públicos (view/update/delete) da MESMA policy chamam esse mesmo
 * doDono(). Testar UMA ação por policy já exercita o branch inteiro: não há
 * ramo extra escondido dentro da mesma classe. É por isso que este teste é
 * tabelado num arquivo só, em vez de seis arquivos repetindo o mesmo
 * cenário — a alternativa (um teste por ação) infla a contagem sem
 * examinar código novo.
 *
 * A garantia que isto trava: apertar a autorização (por exemplo, trocar
 * `can('leads.view-all')` por uma checagem que só olha o dono direto, ou
 * remover a chamada por engano numa refatoração) não pode acontecer sem
 * quebrar visivelmente o gestor. Sem este teste, essa regressão só apareceria
 * em produção — o gestor tentando abrir o anexo ou a tarefa de um vendedor da
 * equipe e recebendo 404.
 */
class GestorAlcancaEquipeTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $gestor;
    private Usuario $leadDaEquipe;
    private Projeto $projetoDaEquipe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->gestor = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $membroDaEquipe = User::factory()->create(['tenant_id' => $this->tenant->id]);

        app(CurrentTenant::class)->set($this->tenant);
        setPermissionsTeamId($this->tenant->id);
        $this->gestor->assignRole('gestor');
        $membroDaEquipe->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
        $this->leadDaEquipe = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $membroDaEquipe->id, 'funil_id' => $funil->id,
        ]);

        $status = Statu::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->projetoDaEquipe = new Projeto();
        $this->projetoDaEquipe->fill([
            'nome' => 'Proposta da equipe', 'usuario_id' => $this->leadDaEquipe->id, 'status_id' => $status->id,
        ]);
        $this->projetoDaEquipe->tenant_id = $this->tenant->id;
        $this->projetoDaEquipe->save();
    }

    public function test_gestor_alcanca_a_equipe_em_cada_policy_que_reconhece_leads_view_all(): void
    {
        $casos = [
            // Wireado em Userarios::updateAnotacao -> $this->authorize('update', $anotacao).
            'AnotacaoPolicy::update' => function (): TestResponse {
                $anotacao = Anotacao::create(['descricao' => 'Nota da equipe', 'usuario_id' => $this->leadDaEquipe->id]);

                return $this->actingAs($this->gestor)
                    ->putJson("/api/anotacao/{$anotacao->id}", ['descricao' => 'Editada pelo gestor']);
            },

            // Wireado em arquivo::destroy -> $this->authorize('delete', $arquivo).
            'ArquivoPolicy::delete' => function (): TestResponse {
                $arquivoDaEquipe = arquivo::create([
                    'nome' => 'contrato.pdf', 'local' => 'arquivos/contrato.pdf', 'usuario_id' => $this->leadDaEquipe->id,
                ]);

                return $this->actingAs($this->gestor)->deleteJson("/api/arquivos/{$arquivoDaEquipe->id}");
            },

            // Wireado em ProjetoController::viewProjeto -> $this->authorize('view', $projeto).
            'ProjetoPolicy::view' => function (): TestResponse {
                return $this->actingAs($this->gestor)->getJson("/api/projeto/{$this->projetoDaEquipe->id}");
            },

            // Wireado em ProjetoController::updateAnotacao -> $this->authorize('update', $projetoAnotacao).
            'ProjetoAnotacaoPolicy::update' => function (): TestResponse {
                $anotacao = ProjetoAnotacao::create(['descricao' => 'Nota', 'projeto_id' => $this->projetoDaEquipe->id]);

                return $this->actingAs($this->gestor)
                    ->putJson("/api/projetoAnotacao/{$anotacao->id}", ['descricao' => 'Editada pelo gestor']);
            },

            // Wireado em ProjetoController::destroyAnexo -> $this->authorize('delete', $projetoAnexo).
            'ProjetoAnexoPolicy::delete' => function (): TestResponse {
                $anexo = ProjetoAnexo::create([
                    'nome' => 'anexo.pdf', 'local' => 'anexos/anexo.pdf', 'projeto_id' => $this->projetoDaEquipe->id,
                ]);

                return $this->actingAs($this->gestor)->deleteJson("/api/projetoAnexo/{$anexo->id}");
            },

            // Wireado em TarefaController::update -> $this->authorize('update', $tarefa).
            'TarefaPolicy::update' => function (): TestResponse {
                $tarefa = Tarefa::create([
                    'usuario_id' => $this->leadDaEquipe->id, 'titulo' => 'Ligar', 'data_limite' => now()->addDay(),
                ]);

                return $this->actingAs($this->gestor)
                    ->putJson("/api/tarefas/{$tarefa->id}", ['titulo' => 'Ligar (editado pelo gestor)']);
            },
        ];

        foreach ($casos as $rotulo => $requisicao) {
            $resposta = $requisicao();

            $this->assertSame(
                200,
                $resposta->status(),
                "{$rotulo}: gestor deveria alcancar recurso da equipe, recebeu {$resposta->status()} - {$resposta->getContent()}",
            );
        }
    }
}
