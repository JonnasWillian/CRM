<?php

namespace Tests\Feature\Navegacao;

use App\Models\User;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class VerbosDeLeituraTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private User $dono;
    private Usuario $lead;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = $this->novoTenant();
        $this->dono = $this->agente($tenant);
        $this->lead = $this->lead($tenant, $this->dono);
    }

    public static function leituras(): array
    {
        return [['/api/estagios'], ['/api/status'], ['/api/metricas'], ['/api/kanban'], ['/api/tarefasPendentes']];
    }

    #[DataProvider('leituras')]
    public function test_leitura_responde_a_get_e_nao_a_post(string $url): void
    {
        $this->actingAs($this->dono)->getJson($url)->assertOk();
        $this->actingAs($this->dono)->postJson($url)->assertMethodNotAllowed();
    }

    public function test_projetos_e_arquivos_do_lead_por_query_string(): void
    {
        $this->actingAs($this->dono)->getJson("/api/projetos?usuario_id={$this->lead->id}")->assertOk();
        $this->actingAs($this->dono)->getJson("/api/arquivos?usuario_id={$this->lead->id}")->assertOk();
        $this->actingAs($this->dono)->postJson('/api/buscarArquivo', ['user_id' => $this->lead->id])->assertNotFound();
    }

    public function test_arquivos_de_lead_alheio_continuam_404(): void
    {
        $colega = $this->agente(app(\App\Support\Tenancy\CurrentTenant::class)->get());

        $this->actingAs($colega)->getJson("/api/arquivos?usuario_id={$this->lead->id}")->assertNotFound();
        $this->actingAs($colega)->getJson("/api/projetos?usuario_id={$this->lead->id}")->assertNotFound();
    }
}
