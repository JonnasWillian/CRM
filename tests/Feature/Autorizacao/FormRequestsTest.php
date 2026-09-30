<?php

namespace Tests\Feature\Autorizacao;

use App\Models\Funil;
use App\Models\Projeto;
use App\Models\Statu;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * `ArquivoRequest` servia dois endpoints incompatíveis: em `arquivo::store` o
 * campo `usuario_id` é id de LEAD; em `ProjetoController::createAnexo` o mesmo
 * campo carrega um id de PROJETO. Só funcionava porque a regra era `required` e
 * nada mais — adicionar `exists:usuarios` quebraria o anexo de projeto.
 *
 * Também cobre o `authorize()` de CLASSE dos FormRequests e o ruling de
 * 403 (sem modelo na rota) vs 404 (com modelo na rota) em `failedAuthorization()`.
 */
class FormRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // O ArquivoService grava no disco privado ('local').
        Storage::fake('local');
    }

    /**
     * Passou a ser 403 e não mais 422 na Rodada de correção 1: o `authorize()`
     * agora resolve o lead do `usuario_id` e checa posse ANTES da validação
     * rodar — `Usuario::find(999999)` não acha nada, e id inexistente e id de
     * terceiro são a mesma negativa. O `exists:usuarios` em `rules()` continua
     * correto e documenta a regra, mas por trás do authorize() nunca mais
     * dispara para este caso especificamente.
     */
    public function test_anexo_de_lead_recusa_id_que_nao_e_lead(): void
    {
        [, $staff] = $this->cenario();

        $this->actingAs($staff)->postJson('/api/arquivos', [
            'usuario_id' => 999999,
            'arquivo' => UploadedFile::fake()->create('a.pdf', 10),
        ])->assertForbidden();
    }

    public function test_anexo_de_projeto_continua_aceitando_id_de_projeto(): void
    {
        [, $staff, , $projeto] = $this->cenario();

        $this->actingAs($staff)->postJson('/api/projetoAnexo', [
            'usuario_id' => $projeto->id,
            'arquivo' => UploadedFile::fake()->create('c.pdf', 10),
        ])->assertSuccessful();
    }

    /** Permissão de classe ausente na CRIAÇÃO é 403: não há recurso para esconder. */
    public function test_sem_permissao_de_classe_a_criacao_responde_403(): void
    {
        [$tenant] = $this->cenario();
        $semPapel = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($semPapel)->postJson('/api/usuarios', [
            'nome' => 'Fulano de Tal',
            'email' => 'fulano@example.com',
            'telefone' => '11999998888',
        ])->assertForbidden();
    }

    /** Sobre um recurso existente, a negativa continua sendo 404. */
    public function test_sem_permissao_de_classe_a_edicao_responde_404(): void
    {
        [$tenant, , $lead] = $this->cenario();
        $semPapel = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($semPapel)
            ->putJson("/api/usuarios/{$lead->id}", ['nome' => 'X', 'email' => $lead->email, 'telefone' => '11999998888'])
            ->assertNotFound();
    }

    /**
     * O buraco que os testes acima não cobriam: recurso PRÓPRIO.
     *
     * Os dois casos anteriores usam lead ALHEIO, e por isso a negativa de
     * classe e a de instância davam o mesmo 404 — ninguém notou que a de
     * classe estava mentindo. Sobre o próprio lead não há existência a
     * esconder (a pessoa acabou de abrir a tela dele), então 404 não protege
     * nada e produz "o lead sumiu ao salvar".
     *
     * 403 é a resposta certa: a negativa é de CLASSE — sem `leads.manage` essa
     * conta não edita lead NENHUM, e o recurso é dela.
     *
     * Não abre oráculo: quem cai neste 403 já passaria no `view` do mesmo
     * lead. Para quem não tem `leads.manage`, a fronteira 403/404 é a mesma
     * que o `view` já expunha — 403 no que enxerga, 404 no que não enxerga.
     */
    public function test_sem_permissao_de_classe_no_proprio_lead_responde_403(): void
    {
        [$tenant] = $this->cenario();

        $semPapel = User::factory()->create(['tenant_id' => $tenant->id]);
        $funil = Funil::factory()->create(['tenant_id' => $tenant->id]);
        $leadProprio = Usuario::factory()->create([
            'tenant_id' => $tenant->id, 'user_id' => $semPapel->id, 'funil_id' => $funil->id,
        ]);

        // Ele enxerga o proprio lead: nao ha o que esconder.
        $this->actingAs($semPapel)->getJson("/api/usuarioPerfil/{$leadProprio->id}")->assertOk();

        $this->actingAs($semPapel)
            ->putJson("/api/usuarios/{$leadProprio->id}", [
                'nome' => 'Nome Editado', 'email' => $leadProprio->email, 'telefone' => '11999998888',
            ])
            ->assertForbidden();

        $this->assertSame($leadProprio->nome, $leadProprio->fresh()->nome);
    }

    /** O mesmo para projeto: negativa de classe sobre coisa própria é 403. */
    public function test_sem_permissao_de_classe_no_proprio_projeto_responde_403(): void
    {
        [$tenant] = $this->cenario();

        $semPapel = User::factory()->create(['tenant_id' => $tenant->id]);
        $funil = Funil::factory()->create(['tenant_id' => $tenant->id]);
        $leadProprio = Usuario::factory()->create([
            'tenant_id' => $tenant->id, 'user_id' => $semPapel->id, 'funil_id' => $funil->id,
        ]);
        $status = Statu::factory()->create(['tenant_id' => $tenant->id]);

        $projetoProprio = new Projeto();
        $projetoProprio->fill(['nome' => 'Proposta propria', 'usuario_id' => $leadProprio->id, 'status_id' => $status->id]);
        $projetoProprio->tenant_id = $tenant->id;
        $projetoProprio->save();

        $this->actingAs($semPapel)->getJson("/api/projeto/{$projetoProprio->id}")->assertOk();

        $this->actingAs($semPapel)
            ->putJson("/api/projeto/{$projetoProprio->id}", [
                'nome' => 'Nome Editado', 'status_id' => $status->id,
            ])
            ->assertForbidden();
    }

    /**
     * A trava do 403 acima: ele NÃO pode escapar para recurso alheio. Sem
     * papel, um lead de colega continua 404 — senão o 403 viraria a confirmação
     * de que o id existe.
     */
    public function test_o_403_de_classe_nao_vaza_para_recurso_alheio(): void
    {
        [$tenant, , $leadDoStaff] = $this->cenario();
        $semPapel = User::factory()->create(['tenant_id' => $tenant->id]);

        $alheio = $this->actingAs($semPapel)->putJson("/api/usuarios/{$leadDoStaff->id}", [
            'nome' => 'X', 'email' => $leadDoStaff->email, 'telefone' => '11999998888',
        ]);
        $inexistente = $this->actingAs($semPapel)->putJson('/api/usuarios/999999', [
            'nome' => 'X', 'email' => 'x@example.com', 'telefone' => '11999998888',
        ]);

        $alheio->assertNotFound();
        $this->assertSame(
            $inexistente->status().' '.$inexistente->getContent(),
            $alheio->status().' '.$alheio->getContent(),
            'lead alheio e id inexistente precisam ser indistinguiveis',
        );
    }

    /**
     * O IDOR que o binding não pega: o dono vem de um campo do CORPO, então a
     * policy de instância nunca rodaria sozinha. Sem a checagem no authorize(),
     * dá para anexar arquivo a um lead que você nem consegue abrir.
     */
    public function test_nao_anexa_arquivo_a_lead_de_colega(): void
    {
        [$tenant, $staff, $lead] = $this->cenario();

        $colega = User::factory()->create(['tenant_id' => $tenant->id]);
        setPermissionsTeamId($tenant->id);
        $colega->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($colega)->postJson('/api/arquivos', [
            'usuario_id' => $lead->id,
            'arquivo' => UploadedFile::fake()->create('a.pdf', 10),
        ])->assertForbidden();

        $this->assertDatabaseMissing('arquivos', ['usuario_id' => $lead->id]);
    }

    public function test_nao_anexa_arquivo_a_projeto_de_colega(): void
    {
        [$tenant, $staff, $lead, $projeto] = $this->cenario();

        $colega = User::factory()->create(['tenant_id' => $tenant->id]);
        setPermissionsTeamId($tenant->id);
        $colega->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($colega)->postJson('/api/projetoAnexo', [
            'usuario_id' => $projeto->id,
            'arquivo' => UploadedFile::fake()->create('c.pdf', 10),
        ])->assertForbidden();
    }

    /**
     * Item 3 da rodada B: `authorize()` resolve o lead via
     * `Usuario::find($this->integer('usuario_id'))` — o cast em PHP lê o
     * prefixo numérico e acha o lead certo, mesmo com lixo depois do número.
     * O controller, porém, gravava `$request->usuario_id` CRU (a string com o
     * lixo) em vez do id do model que acabou de ser autorizado. O que ia para
     * o banco não era o que tinha sido checado.
     *
     * Chama o controller DIRETO, sem passar pelo ciclo de FormRequest — de
     * propósito: a regra `integer` do item 7 (rules() de ArquivoRequest) já
     * recusaria "{$lead->id}abc" na validação, antes de o controller rodar,
     * o que tornaria este cenário inalcançável via HTTP e esconderia o que
     * este teste quer isolar. O que está em jogo aqui é uma camada mais
     * abaixo: dado um valor cru que DIVERGE do id do model, o controller
     * grava o model autorizado ou o texto cru? A validação de outra camada
     * não deveria ser pré-requisito para essa garantia.
     */
    public function test_anexo_de_lead_grava_o_id_do_lead_autorizado_e_nao_o_texto_cru(): void
    {
        [, $staff, $lead] = $this->cenario();

        $request = \App\Http\Requests\ArquivoRequest::create('/api/arquivos', 'POST', [
            'usuario_id' => "{$lead->id}abc",
        ]);
        $request->setUserResolver(fn () => $staff);
        $request->files->set('arquivo', UploadedFile::fake()->create('a.pdf', 10));

        $controller = app(\App\Http\Controllers\arquivo::class);
        $resposta = $controller->store($request);

        $this->assertSame(200, $resposta->getStatusCode());
        $this->assertDatabaseHas('arquivos', ['usuario_id' => $lead->id]);
    }

    /** Mesmo defeito, mesmo padrão, no anexo de projeto. */
    public function test_anexo_de_projeto_grava_o_id_do_projeto_autorizado_e_nao_o_texto_cru(): void
    {
        [, $staff, , $projeto] = $this->cenario();

        $request = \App\Http\Requests\ProjetoAnexoRequest::create('/api/projetoAnexo', 'POST', [
            'usuario_id' => "{$projeto->id}abc",
        ]);
        $request->setUserResolver(fn () => $staff);
        $request->files->set('arquivo', UploadedFile::fake()->create('c.pdf', 10));

        $controller = app(\App\Http\Controllers\ProjetoController::class);
        $resposta = $controller->createAnexo($request);

        $this->assertSame(201, $resposta->getStatusCode());
        $this->assertDatabaseHas('projetoAnexos', ['projeto_id' => $projeto->id]);
    }

    /**
     * Item 7 da rodada C: o `findOrFail` do projeto, acrescentado numa rodada
     * anterior, tinha ficado DENTRO de um `try { ... } catch (\Exception) {
     * 500 'Erro ao salvar anexo' }` — o mesmo padrão de catch amplo já
     * removido do TarefaPadraoController. Hoje o ramo é inalcançável via
     * HTTP: `ProjetoAnexoRequest::authorize()` já resolveu e autorizou o
     * projeto antes do controller rodar, então um id inexistente nunca
     * chega até aqui por essa porta. Por isso o teste chama o controller
     * DIRETO — sem passar pelo ciclo de validação do FormRequest, igual ao
     * teste acima — para isolar exatamente o que o try/catch decide: um id
     * que não existe precisa propagar `ModelNotFoundException` (que o
     * Handler global traduz para 404), não virar 500 com mensagem enganosa.
     */
    public function test_anexo_de_projeto_inexistente_propaga_notfound_e_nao_vira_500(): void
    {
        [, $staff] = $this->cenario();

        $request = \App\Http\Requests\ProjetoAnexoRequest::create('/api/projetoAnexo', 'POST', [
            'usuario_id' => 999999,
        ]);
        $request->setUserResolver(fn () => $staff);
        $request->files->set('arquivo', UploadedFile::fake()->create('c.pdf', 10));

        $controller = app(\App\Http\Controllers\ProjetoController::class);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $controller->createAnexo($request);
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Usuario, 3: Projeto}
     */
    private function cenario(): array
    {
        $tenant = Tenant::factory()->create();
        $staff = User::factory()->create(['tenant_id' => $tenant->id]);
        app(CurrentTenant::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
        $staff->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $lead = Usuario::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $staff->id, 'funil_id' => $funil->id]);
        $status = Statu::factory()->create(['tenant_id' => $tenant->id]);

        $projeto = new Projeto();
        $projeto->fill(['nome' => 'Proposta', 'usuario_id' => $lead->id, 'status_id' => $status->id]);
        $projeto->tenant_id = $tenant->id;
        $projeto->save();

        return [$tenant, $staff, $lead, $projeto];
    }
}
