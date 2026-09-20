<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Activity;
use App\Models\Anotacao;
use App\Models\Projeto;
use App\Models\ProjetoAnexo;
use App\Models\Statu;
use App\Models\Tags;
use App\Models\Tarefa;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Models\UsuarioTagHistorico;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Os observers alimentam o activity log em paralelo à timeline antiga.
 *
 * O que importa em cada evento: o lead_id correto (é por ele que o endpoint
 * agrupa) e o tenant_id correto (o log é isolado como o resto do domínio).
 */
class LeadActivityObserverTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $staff;
    private Usuario $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
        app(CurrentTenant::class)->set($this->tenant);
        $this->lead = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->staff->id,
        ]);
    }

    private function atividades(string $event)
    {
        return Activity::where('event', $event)->get();
    }

    public function test_criar_lead_registra_atividade(): void
    {
        $atividade = $this->atividades('lead_criado')->firstWhere('lead_id', $this->lead->id);

        $this->assertNotNull($atividade);
        $this->assertSame($this->tenant->id, $atividade->tenant_id);
    }

    public function test_criar_anotacao_registra_atividade_com_lead_correto(): void
    {
        $anotacao = Anotacao::create([
            'descricao' => 'Ligou pedindo proposta',
            'usuario_id' => $this->lead->id,
        ]);

        $atividade = $this->atividades('anotacao')->first();

        $this->assertNotNull($atividade);
        $this->assertSame($this->lead->id, $atividade->lead_id);
        $this->assertSame($this->tenant->id, $atividade->tenant_id);
        $this->assertSame(Anotacao::class, $atividade->subject_type);
        $this->assertSame($anotacao->id, $atividade->subject_id);
    }

    public function test_apagar_anotacao_registra_evento_de_remocao(): void
    {
        $anotacao = Anotacao::create([
            'descricao' => 'Some depois',
            'usuario_id' => $this->lead->id,
        ]);

        $anotacao->delete();

        $remocao = $this->atividades('anotacao_removida')->first();

        $this->assertNotNull($remocao, 'soft delete precisa registrar o evento de remocao');
        $this->assertSame($this->lead->id, $remocao->lead_id);
    }

    public function test_anexo_de_projeto_resolve_lead_a_dois_saltos(): void
    {
        $status = Statu::factory()->create(['tenant_id' => $this->tenant->id]);
        $projeto = Projeto::create([
            'nome' => 'Site institucional',
            'usuario_id' => $this->lead->id,
            'status_id' => $status->id,
        ]);

        $anexo = ProjetoAnexo::create([
            'nome' => 'contrato.pdf',
            'local' => 'arquivos/x.pdf',
            'projeto_id' => $projeto->id,
        ]);

        $atividade = $this->atividades('projeto_anexo')->first();

        $this->assertNotNull($atividade);
        $this->assertSame($this->lead->id, $atividade->lead_id, 'lead_id deve vir de projeto.usuario_id');
    }

    public function test_trocar_tag_registra_status_alterado_e_mantem_historico_antigo(): void
    {
        $tagNova = Tags::factory()->create(['tenant_id' => $this->tenant->id]);
        $tagAnterior = $this->lead->tag_id;

        $this->lead->update(['tag_id' => $tagNova->id]);

        $atividade = $this->atividades('status_alterado')->first();

        $this->assertNotNull($atividade);
        $this->assertSame($this->lead->id, $atividade->lead_id);

        // A tabela antiga continua sendo alimentada durante a transição, agora
        // a partir do observer em vez de duplicada nos controllers.
        $historico = UsuarioTagHistorico::where('usuario_id', $this->lead->id)->first();
        $this->assertNotNull($historico, 'UsuarioTagHistorico precisa continuar sendo gravado');
        $this->assertSame($tagAnterior, $historico->tag_id_anterior);
        $this->assertSame($tagNova->id, $historico->tag_id_novo);
    }

    public function test_concluir_tarefa_registra_evento_separado_da_criacao(): void
    {
        $tarefa = Tarefa::create([
            'usuario_id' => $this->lead->id,
            'titulo' => 'Enviar proposta',
            'data_limite' => now()->addDay(),
        ]);

        $this->assertCount(1, $this->atividades('tarefa_criada'));
        $this->assertCount(0, $this->atividades('tarefa_concluida'));

        $tarefa->update(['concluido' => true, 'concluido_em' => now()]);

        $this->assertCount(1, $this->atividades('tarefa_concluida'));
    }

    public function test_atividade_nao_vaza_entre_tenants(): void
    {
        Anotacao::create(['descricao' => 'do tenant A', 'usuario_id' => $this->lead->id]);

        $outroTenant = Tenant::factory()->create();
        app(CurrentTenant::class)->set($outroTenant);

        $this->assertCount(0, $this->atividades('anotacao'));
    }
}
