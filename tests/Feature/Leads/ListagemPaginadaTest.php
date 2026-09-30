<?php

namespace Tests\Feature\Leads;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Statu;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class ListagemPaginadaTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;
    private User $vendedor;
    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->novoTenant();
        $this->vendedor = $this->agente($this->tenant);
        $this->gestor = $this->agente($this->tenant, 'gestor');
    }

    private function listar(User $quem, array $filtros = [])
    {
        return $this->actingAs($quem)->getJson('/api/leads?'.http_build_query($filtros))->assertOk();
    }

    private function ids($resposta): array
    {
        return collect($resposta->json('data'))->pluck('id')->sort()->values()->all();
    }

    public function test_pagina_no_servidor(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->lead($this->tenant, $this->vendedor);
        }

        $pagina1 = $this->listar($this->vendedor, ['per_page' => 25]);
        $pagina1->assertJsonCount(25, 'data')->assertJsonPath('total', 30)->assertJsonPath('last_page', 2);

        $this->listar($this->vendedor, ['per_page' => 25, 'page' => 2])->assertJsonCount(5, 'data');
    }

    public function test_per_page_tem_teto(): void
    {
        $this->listar($this->vendedor, ['per_page' => 500])->assertJsonPath('per_page', 100);
    }

    public function test_mais_recente_primeiro(): void
    {
        $this->travel(-2)->days();
        $antigo = $this->lead($this->tenant, $this->vendedor);
        $this->travelBack();
        $novo = $this->lead($this->tenant, $this->vendedor);

        $this->listar($this->vendedor)
            ->assertJsonPath('data.0.id', $novo->id)
            ->assertJsonPath('data.1.id', $antigo->id);
    }

    public function test_visibilidade_e_tenant_continuam_valendo(): void
    {
        $meu = $this->lead($this->tenant, $this->vendedor);
        $doColega = $this->lead($this->tenant, $this->agente($this->tenant));

        $outro = $this->novoTenant();
        $this->lead($outro, $this->agente($outro));
        $this->ativar($this->tenant);

        $this->assertSame([$meu->id], $this->ids($this->listar($this->vendedor)));
        $this->assertSame(collect([$meu->id, $doColega->id])->sort()->values()->all(), $this->ids($this->listar($this->gestor)));
    }

    public function test_lead_na_lixeira_nao_aparece(): void
    {
        $this->lead($this->tenant, $this->vendedor)->delete();

        $this->listar($this->vendedor)->assertJsonPath('total', 0);
    }

    public function test_busca_por_nome_email_descricao_e_telefone(): void
    {
        $maria = $this->lead($this->tenant, $this->vendedor, ['nome' => 'Maria Souza', 'telefone' => '+5511988887777']);
        $this->lead($this->tenant, $this->vendedor, ['nome' => 'João Lima', 'email' => 'joao@firma.com', 'descricao' => 'Veio da feira']);

        $this->assertSame([$maria->id], $this->ids($this->listar($this->vendedor, ['busca' => 'souza'])));
        $this->assertSame([$maria->id], $this->ids($this->listar($this->vendedor, ['busca' => '(11) 98888'])));
        $this->assertCount(1, $this->listar($this->vendedor, ['busca' => 'firma.com'])->json('data'));
        $this->assertCount(1, $this->listar($this->vendedor, ['busca' => 'feira'])->json('data'));
    }

    /** Review Focus 4 */
    public function test_curingas_do_like_sao_texto_literal(): void
    {
        $comPercentual = $this->lead($this->tenant, $this->vendedor, ['nome' => 'Desconto 50% Ltda']);
        $this->lead($this->tenant, $this->vendedor, ['nome' => 'Compra 500 unidades']);
        $this->lead($this->tenant, $this->vendedor, ['nome' => 'Nome_com_sublinhado']);

        $this->assertSame([$comPercentual->id], $this->ids($this->listar($this->vendedor, ['busca' => '50%'])));
        $this->assertCount(1, $this->listar($this->vendedor, ['busca' => 'e_c'])->json('data'));

        // "(11)" tem só 2 dígitos: abaixo do mínimo de 3, o telefone não
        // entra na busca (casaria com quase todo número de SP). Sobra a busca
        // literal por "(11)" em nome, email e descrição — os parênteses não
        // são curinga do LIKE.
        $this->lead($this->tenant, $this->vendedor, ['nome' => 'Sem parênteses', 'telefone' => '+5511977776666']);
        $comParenteses = $this->lead($this->tenant, $this->vendedor, ['nome' => 'Filial (11) Centro']);

        $this->assertSame([$comParenteses->id], $this->ids($this->listar($this->vendedor, ['busca' => '(11)'])));
    }

    public function test_filtro_por_estagio_e_por_situacao(): void
    {
        // Nada além do setUp() rodou ainda neste teste: sem um lead() prévio,
        // o tenant pode não ter funil padrão. Mesmo fallback de CenarioDeTenant::lead().
        $funil = Funil::where('is_default', true)->first()
            ?? Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
        $aberto = Estagio::factory()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);
        $ganho = Estagio::factory()->ganho()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);

        $emAberto = $this->lead($this->tenant, $this->vendedor, ['estagio_id' => $aberto->id]);
        $fechado = $this->lead($this->tenant, $this->vendedor, ['estagio_id' => $ganho->id]);
        $comProjetoAberto = $this->lead($this->tenant, $this->vendedor, ['estagio_id' => $aberto->id]);
        $this->projeto($comProjetoAberto, ['status_id' => Statu::factory()->create(['tenant_id' => $this->tenant->id])->id]);

        $this->assertSame(
            collect([$emAberto->id, $comProjetoAberto->id])->sort()->values()->all(),
            $this->ids($this->listar($this->vendedor, ['estagios' => [$aberto->id]]))
        );
        $this->assertSame([$fechado->id], $this->ids($this->listar($this->vendedor, ['status' => 'arquivado'])));
        $this->assertSame([$comProjetoAberto->id], $this->ids($this->listar($this->vendedor, ['status' => 'aberto'])));
        $this->assertSame(
            collect([$emAberto->id, $fechado->id])->sort()->values()->all(),
            $this->ids($this->listar($this->vendedor, ['status' => 'sem_projeto']))
        );
    }

    public function test_filtro_por_periodo(): void
    {
        $this->travel(-40)->days();
        $this->lead($this->tenant, $this->vendedor);
        $this->travelBack();
        $recente = $this->lead($this->tenant, $this->vendedor);

        $this->assertSame([$recente->id], $this->ids($this->listar($this->vendedor, ['de' => now()->subDays(30)->toDateString()])));
    }

    /**
     * O Dashboard manda o instante completo do início e do fim do dia LOCAL
     * (no Brasil, 00:00-03:00 = 03:00Z). Aplicar startOfDay/endOfDay em UTC
     * sobre ele deslocava o período em um dia.
     */
    public function test_periodo_com_instante_iso_e_usado_como_veio(): void
    {
        $this->travelTo(Carbon::parse('2026-09-11T02:00:00Z'));
        $dentro = $this->lead($this->tenant, $this->vendedor);
        $this->travelTo(Carbon::parse('2026-09-11T03:30:00Z'));
        $this->lead($this->tenant, $this->vendedor);
        $this->travelBack();

        $filtro = ['de' => '2026-09-10T03:00:00.000Z', 'ate' => '2026-09-11T02:59:59.999Z'];

        $this->assertSame([$dentro->id], $this->ids($this->listar($this->vendedor, $filtro)));
    }

    public function test_periodo_invertido_e_recusado(): void
    {
        $this->actingAs($this->vendedor)
            ->getJson('/api/leads?de=2026-09-10&ate=2026-09-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ate');
    }

    public function test_rota_antiga_nao_existe_mais(): void
    {
        $this->actingAs($this->vendedor)->postJson('/api/pegarUsuarios')->assertNotFound();
    }
}
