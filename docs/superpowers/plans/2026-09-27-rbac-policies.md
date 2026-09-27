# RBAC — policies e fechamento do IDOR interno

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fechar o IDOR interno — hoje qualquer agente autenticado lê e edita os leads, projetos, tarefas, anotações e arquivos de qualquer colega do mesmo tenant, bastando adivinhar um id.

**Architecture:** Oito policies em `app/Policies`, registradas explicitamente. As rotas legadas com `{id}` passam a usar route model binding, o que faz o `TenantScope` devolver 404 para id de outro tenant antes mesmo da policy rodar. A regra comum: quem tem `leads.view-all` passa dentro do tenant; quem não tem, passa só no que é seu. Negativas usam `Response::denyAsNotFound()` para produzir 404 em vez de 403, para não confirmar que o id existe.

**Tech Stack:** Laravel 11.36, spatie/laravel-permission 6.25 (modo teams, já instalado e semeado), PHPUnit.

**Spec:** `docs/superpowers/specs/2026-09-15-autorizacao-rbac-design.md` (revisado em 2026-09-27)

## Global Constraints

- **PHP 8.2** — não usar sintaxe de 8.3+.
- **`spatie/laravel-permission ^6.25`** em modo `teams`, com `team_foreign_key => tenant_id`. Já instalado; não alterar a configuração.
- **Mapeamento de permissões, confirmado em 27/09 e já semeado:** `leads.view-all` (admin, gestor), `leads.manage` (todos), `projetos.manage` (todos), `tarefas.manage` (todos), `configuracoes.manage` (admin, gestor), `agentes.manage` (admin).
- **Acesso negado responde 404**, nunca 403, em tudo que é dado de carteira. 403 confirma que o recurso existe.
- **`tenant_id` nunca entra em `$fillable`** — Global Constraint da multi-tenancy, vale para qualquer model tocado aqui.
- **Nenhuma URL consumida pelo frontend muda.** O binding resolve pelo *nome do parâmetro*; a rota pode manter o caminho.
- **Testes rodam contra `CRMLeader_test`**, fixado em `phpunit.xml`. Nunca apontar para `CRMLeader`.
- Toda tarefa termina com `php artisan test` inteiro verde, não só o teste da tarefa.
- **Não commitar.** Neste projeto quem commita é o dono do repositório, que
  revisa o diff completo antes. Os passos de commit ao fim de cada tarefa
  existem no formato do plano e devem ser **pulados**: rode os testes, deixe as
  mudanças na árvore de trabalho e pare.
- **Trabalhar no diretório principal**, em `master`. Sem git worktree — é regra
  deste projeto.

## Review Focus

Cinco condições que o spec implica, que nenhum teste de tarefa exercita por padrão, e que quebram na mão de quem usa. Cada linha tem o teste apontado na tarefa que possui o código.

1. **Gestor acessando lead de outro agente do mesmo tenant deve PASSAR.** É o que `leads.view-all` concede, e é o erro mais fácil de cometer: escrever a policy só com a checagem de dono e trancar o gestor para fora da equipe dele. → Tarefa 2.
2. **Usuário sem papel nenhum.** Só o primeiro usuário de cada tenant virou admin na migration de seed; os demais estão sem papel. Devem receber negativa limpa, não erro de permissão inexistente. → Tarefa 2.
3. **Recurso de outro tenant responde 404 e não 403.** Vale para todos os models, não só `Usuario` — o teste tabelado precisa cobrir cada um. → Tarefa 7.
4. **Recurso soft-deleted.** `Anotacao`, `arquivo`, `ProjetoAnotacao` e `ProjetoAnexo` usam SoftDeletes; o binding padrão devolve 404 para linha apagada. Isso é o desejado, mas precisa estar fixado para ninguém "consertar" adicionando `withTrashed` sem pensar. → Tarefa 4.
5. **Rotas cujo `{id}` é o id do LEAD, não do próprio recurso.** `/tarefas/{usuarioId}`, `/anotacao/{id}` no GET, `/projetoAnotacao/{id}` e `/projetoAnexo/{id}` no GET. Autorizar a entidade errada aqui abre o buraco em vez de fechá-lo. → Tarefas 3 e 4.

---

### Task 1: `Usuario::scopeVisibleTo` e as quatro listagens

**Files:**
- Modify: `app/Models/Usuario.php`
- Modify: `app/Http/Controllers/Userarios.php` (métodos `view`, `kanban`, `metricas`)
- Modify: `app/Http/Controllers/TarefaController.php` (método `pendentes`)
- Test: `tests/Feature/Autorizacao/VisibilidadeDeLeadsTest.php`

**Interfaces:**
- Consumes: nada.
- Produces: `Usuario::scopeVisibleTo(Builder $query, User $user): Builder` — usado pelas Tarefas 2 a 5 em nenhum lugar; é só das listagens. As policies não o usam.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Autorizacao;

use App\Models\Funil;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * A distinção que carrega o sistema: `leads.view-all` separa quem enxerga a
 * carteira inteira da empresa de quem enxerga só a própria.
 */
class VisibilidadeDeLeadsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $gestor;
    private User $vendedor;
    private Usuario $leadDoGestor;
    private Usuario $leadDoVendedor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->gestor = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->vendedor = User::factory()->create(['tenant_id' => $this->tenant->id]);

        app(CurrentTenant::class)->set($this->tenant);
        setPermissionsTeamId($this->tenant->id);
        $this->gestor->assignRole('gestor');
        $this->vendedor->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);

        $this->leadDoGestor = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->gestor->id, 'funil_id' => $funil->id,
        ]);
        $this->leadDoVendedor = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->vendedor->id, 'funil_id' => $funil->id,
        ]);
    }

    public function test_vendedor_lista_apenas_a_propria_carteira(): void
    {
        $ids = collect($this->actingAs($this->vendedor)->postJson('/api/pegarUsuarios')->assertOk()->json())
            ->pluck('id')->all();

        $this->assertContains($this->leadDoVendedor->id, $ids);
        $this->assertNotContains($this->leadDoGestor->id, $ids);
    }

    public function test_gestor_lista_a_carteira_inteira_do_tenant(): void
    {
        $ids = collect($this->actingAs($this->gestor)->postJson('/api/pegarUsuarios')->assertOk()->json())
            ->pluck('id')->all();

        $this->assertContains($this->leadDoGestor->id, $ids);
        $this->assertContains($this->leadDoVendedor->id, $ids, 'gestor tem leads.view-all e deve ver a equipe');
    }

    public function test_o_kanban_segue_a_mesma_regra(): void
    {
        $dados = $this->actingAs($this->gestor)->postJson('/api/kanban')->assertOk()->json();
        $ids = array_column($dados['leads'], 'id');

        $this->assertContains($this->leadDoVendedor->id, $ids);
    }

    public function test_as_metricas_seguem_a_mesma_regra(): void
    {
        $vendedor = $this->actingAs($this->vendedor)->postJson('/api/metricas')->assertOk()->json();
        $gestor = $this->actingAs($this->gestor)->postJson('/api/metricas')->assertOk()->json();

        $this->assertSame(1, $vendedor['total_leads']);
        $this->assertSame(2, $gestor['total_leads']);
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=VisibilidadeDeLeadsTest`
Expected: FAIL — hoje as listagens filtram por `auth()->id()` fixo, então `test_gestor_lista_a_carteira_inteira_do_tenant` falha (o gestor não vê o lead do vendedor).

- [ ] **Step 3: Adicionar o scope ao model**

Em `app/Models/Usuario.php`, acrescentar o `use` e o método:

```php
use Illuminate\Database\Eloquent\Builder;
```

```php
    /**
     * Restringe a listagem ao que o agente pode enxergar.
     *
     * Com `leads.view-all` não há filtro adicional — o TenantScope já limita à
     * empresa. Sem a permission, o agente vê só a própria carteira.
     *
     * Este scope serve as LISTAGENS. O acesso a um lead específico é decidido
     * pela UsuarioPolicy, que aplica a mesma regra para uma linha só.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('leads.view-all')) {
            return $query;
        }

        return $query->where('usuarios.user_id', $user->id);
    }
```

- [ ] **Step 4: Aplicar o scope nas quatro listagens**

Em `app/Http/Controllers/Userarios.php`, método `view()`, trocar:

```php
        $usuarios = Usuario::where('user_id', auth()->id())
```

por:

```php
        $usuarios = Usuario::visibleTo(auth()->user())
```

No método `kanban()`, trocar:

```php
        $leads = Usuario::where('user_id', $userId)
            ->where('funil_id', $funil->id)
```

por:

```php
        $leads = Usuario::visibleTo(auth()->user())
            ->where('funil_id', $funil->id)
```

No método `metricas()`, trocar todas as seis ocorrências de
`Usuario::where('user_id', $userId)` por `Usuario::visibleTo(auth()->user())`,
e a linha `$leadIds = Usuario::where('user_id', $userId)->pluck('id');` por
`$leadIds = Usuario::visibleTo(auth()->user())->pluck('id');`.

Em `app/Http/Controllers/TarefaController.php`, método `pendentes()`, trocar o
filtro de leads por `Usuario::visibleTo(auth()->user())->pluck('id')`.

- [ ] **Step 5: Rodar o teste da tarefa e a suíte inteira**

Run: `php artisan test --filter=VisibilidadeDeLeadsTest && php artisan test`
Expected: PASS nos dois.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Usuario.php app/Http/Controllers/Userarios.php app/Http/Controllers/TarefaController.php tests/Feature/Autorizacao/VisibilidadeDeLeadsTest.php
git commit -m "feat: scopeVisibleTo separa carteira propria de carteira do tenant"
```

---

### Task 2: `AuthorizesRequests`, `UsuarioPolicy` e as rotas de lead

**Files:**
- Modify: `app/Http/Controllers/Controller.php`
- Create: `app/Policies/UsuarioPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/Userarios.php`
- Modify: `app/Http/Controllers/LeadAtividadeController.php`
- Test: `tests/Feature/Autorizacao/UsuarioPolicyTest.php`

**Interfaces:**
- Consumes: nada da Tarefa 1.
- Produces: `App\Policies\UsuarioPolicy` com `view(User, Usuario)`, `update(User, Usuario)`, `delete(User, Usuario)`, todos devolvendo `Response|bool`. As Tarefas 3 e 4 seguem exatamente este formato.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Autorizacao;

use App\Models\Funil;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UsuarioPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $gestor;
    private User $vendedor;
    private User $semPapel;
    private Usuario $leadDoGestor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->gestor = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->vendedor = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->semPapel = User::factory()->create(['tenant_id' => $this->tenant->id]);

        app(CurrentTenant::class)->set($this->tenant);
        setPermissionsTeamId($this->tenant->id);
        $this->gestor->assignRole('gestor');
        $this->vendedor->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
        $this->leadDoGestor = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->gestor->id, 'funil_id' => $funil->id,
        ]);
    }

    public function test_vendedor_nao_alcanca_lead_de_colega(): void
    {
        $this->actingAs($this->vendedor)
            ->getJson("/api/usuarioPerfil/{$this->leadDoGestor->id}")
            ->assertNotFound();
    }

    /** Review Focus 1: o erro mais fácil é trancar o gestor para fora da equipe dele. */
    public function test_gestor_alcanca_lead_de_outro_agente_do_tenant(): void
    {
        $outro = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $funil = Funil::factory()->create(['tenant_id' => $this->tenant->id]);
        $lead = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $outro->id, 'funil_id' => $funil->id,
        ]);

        $this->actingAs($this->gestor)
            ->getJson("/api/usuarioPerfil/{$lead->id}")
            ->assertOk();
    }

    /** Review Focus 2: quem não recebeu papel nenhum não pode virar erro. */
    public function test_usuario_sem_papel_recebe_negativa_limpa(): void
    {
        $this->actingAs($this->semPapel)
            ->getJson("/api/usuarioPerfil/{$this->leadDoGestor->id}")
            ->assertNotFound();
    }

    public function test_dono_edita_o_proprio_lead(): void
    {
        $this->actingAs($this->gestor)
            ->putJson("/api/usuarios/{$this->leadDoGestor->id}", [
                'nome' => 'Nome Editado',
                'email' => $this->leadDoGestor->email,
                'telefone' => '11999998888',
            ])
            ->assertOk();
    }

    public function test_vendedor_nao_edita_lead_de_colega(): void
    {
        $this->actingAs($this->vendedor)
            ->putJson("/api/usuarios/{$this->leadDoGestor->id}", [
                'nome' => 'Sequestrado',
                'email' => $this->leadDoGestor->email,
                'telefone' => '11999998888',
            ])
            ->assertNotFound();

        $this->assertSame($this->leadDoGestor->nome, $this->leadDoGestor->fresh()->nome);
    }

    /**
     * O check provisório em LeadAtividadeController comparava user_id com
     * auth()->id() e portanto trancava o gestor para fora da equipe dele.
     * A policy corrige esse comportamento, que está em produção.
     */
    public function test_gestor_ve_atividades_de_lead_da_equipe(): void
    {
        $outro = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $funil = Funil::factory()->create(['tenant_id' => $this->tenant->id]);
        $lead = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $outro->id, 'funil_id' => $funil->id,
        ]);

        $this->actingAs($this->gestor)
            ->getJson("/api/leads/{$lead->id}/atividades")
            ->assertOk();
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=UsuarioPolicyTest`
Expected: FAIL — `test_vendedor_nao_alcanca_lead_de_colega` recebe 200 (o IDOR), e `test_gestor_ve_atividades_de_lead_da_equipe` recebe 404 (o bug do check provisório).

- [ ] **Step 3: Habilitar `$this->authorize()` no controller base**

O skeleton do Laravel 11 removeu a trait. Sem isto, `$this->authorize()` não
existe e o passo seguinte falha com "Call to undefined method".

`app/Http/Controllers/Controller.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;
}
```

- [ ] **Step 4: Escrever a policy**

`app/Policies/UsuarioPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Usuario;
use Illuminate\Auth\Access\Response;

/**
 * Lead: o dono está direto na linha, em `usuarios.user_id`.
 *
 * Regra comum a todas as policies deste sistema, em uma frase: quem tem
 * `leads.view-all` passa dentro do tenant; quem não tem, passa só no que é seu.
 * O TenantScope já garante que nada de outro tenant chega até aqui.
 *
 * Negativas devolvem 404 e não 403: 403 confirma que o id existe, e isso
 * deixaria enumerar a carteira dos colegas um id por vez.
 */
class UsuarioPolicy
{
    public function view(User $user, Usuario $usuario): Response
    {
        return $this->doDono($user, $usuario);
    }

    public function update(User $user, Usuario $usuario): Response
    {
        return $this->doDono($user, $usuario);
    }

    public function delete(User $user, Usuario $usuario): Response
    {
        return $this->doDono($user, $usuario);
    }

    private function doDono(User $user, Usuario $usuario): Response
    {
        if ($user->can('leads.view-all') || $usuario->user_id === $user->id) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }
}
```

- [ ] **Step 5: Registrar a policy**

Em `app/Providers/AppServiceProvider.php`, acrescentar no topo:

```php
use App\Policies\UsuarioPolicy;
use Illuminate\Support\Facades\Gate;
```

e no fim de `boot()`:

```php
        // Registro explícito e não por convenção: a descoberta automática do
        // Laravel deriva o nome da classe, e o model `arquivo` é minúsculo —
        // ela procuraria `arquivoPolicy`. Explícito evita depender disso.
        Gate::policy(Usuario::class, UsuarioPolicy::class);
```

- [ ] **Step 6: Converter as rotas de lead para binding**

Em `routes/api.php`, trocar as quatro rotas:

```php
    Route::get('/usuarioPerfil/{usuario}', [Userarios::class, 'viewUsuario']);
    Route::put('/usuarios/{usuario}', [Userarios::class, 'update']);
    Route::get('/timeline/{usuario}', [Userarios::class, 'timeline']);
    Route::patch('/usuarios/{usuario}/estagio', [Userarios::class, 'patchEstagio']);
    Route::patch('/usuarios/{usuario}/funil', [Userarios::class, 'moverFunil']);
```

`DELETE /usuarios/{usuario}` já usa o nome certo e não muda.

- [ ] **Step 7: Autorizar nos métodos do controller**

Em `app/Http/Controllers/Userarios.php`, trocar as assinaturas e remover os
`findOrFail`. Exemplo para `viewUsuario`:

```php
    public function viewUsuario(Usuario $usuario)
    {
        $this->authorize('view', $usuario);

        return response()->json([$usuario]);
    }
```

Para `update`:

```php
    public function update(UsuarioRequest $request, Usuario $usuario)
    {
        $this->authorize('update', $usuario);

        try {
            app(AplicarTransicao::class)($usuario, $request->validated(), RegrasDePerda::extrair($request));

            return response()->json(['message' => 'Usuário atualizado com sucesso'], 200);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json(['erros' => $error->errors()], 422);
        }
    }
```

Aplicar o mesmo padrão em `timeline` (`view`), `destroy` (`delete`),
`patchEstagio` (`update`) e `moverFunil` (`update`), sempre removendo o
`Usuario::findOrFail($id)` e o `catch (\Exception)` que virava 404 — o binding
já faz isso.

**Atenção:** em `update`, o `UsuarioRequest::updateRules()` usa
`$this->route('id')` no `ignore()` da regra de e-mail único. Trocar por
`$this->route('usuario')?->id`, senão a edição passa a acusar e-mail duplicado
contra o próprio lead.

- [ ] **Step 8: Trocar o check provisório por policy**

Em `app/Http/Controllers/LeadAtividadeController.php`, remover:

```php
        abort_unless($usuario->user_id === auth()->id(), 404);
```

e colocar:

```php
        $this->authorize('view', $usuario);
```

- [ ] **Step 9: Rodar os testes**

Run: `php artisan test --filter=UsuarioPolicyTest && php artisan test`
Expected: PASS nos dois.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Controller.php app/Policies/UsuarioPolicy.php app/Providers/AppServiceProvider.php routes/api.php app/Http/Controllers/Userarios.php app/Http/Controllers/LeadAtividadeController.php app/Http/Requests/UsuarioRequest.php tests/Feature/Autorizacao/UsuarioPolicyTest.php
git commit -m "feat: UsuarioPolicy fecha o IDOR de lead e corrige acesso do gestor"
```

---

### Task 3: Policies de um salto — Projeto, Tarefa, Anotacao, Arquivo

**Files:**
- Create: `app/Policies/ProjetoPolicy.php`, `app/Policies/TarefaPolicy.php`, `app/Policies/AnotacaoPolicy.php`, `app/Policies/ArquivoPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/ProjetoController.php`, `app/Http/Controllers/TarefaController.php`, `app/Http/Controllers/Userarios.php`, `app/Http/Controllers/arquivo.php`
- Test: `tests/Feature/Autorizacao/UmSaltoPolicyTest.php`

**Interfaces:**
- Consumes: o formato de `UsuarioPolicy` da Tarefa 2 — mesma assinatura `Response`, mesma `denyAsNotFound()`.
- Produces: quatro policies com `view`/`update`/`delete`, todas resolvendo o dono por `→ usuario.user_id`.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Autorizacao;

use App\Models\Anotacao;
use App\Models\arquivo;
use App\Models\Funil;
use App\Models\Projeto;
use App\Models\Statu;
use App\Models\Tarefa;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Projeto, Tarefa, Anotacao e Arquivo não têm dono próprio: o dono é o do lead
 * a que pertencem. Um salto até `usuarios.user_id`.
 */
class UmSaltoPolicyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $dono;
    private User $intruso;
    private Usuario $lead;
    private Projeto $projeto;
    private Tarefa $tarefa;
    private Anotacao $anotacao;
    private arquivo $arquivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->dono = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->intruso = User::factory()->create(['tenant_id' => $this->tenant->id]);

        app(CurrentTenant::class)->set($this->tenant);
        setPermissionsTeamId($this->tenant->id);
        $this->dono->assignRole('vendedor');
        $this->intruso->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
        $this->lead = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->dono->id, 'funil_id' => $funil->id,
        ]);

        $status = Statu::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->projeto = new Projeto();
        $this->projeto->fill(['nome' => 'Proposta inicial', 'usuario_id' => $this->lead->id, 'status_id' => $status->id]);
        $this->projeto->tenant_id = $this->tenant->id;
        $this->projeto->save();

        $this->tarefa = Tarefa::create(['usuario_id' => $this->lead->id, 'titulo' => 'Ligar', 'data_limite' => now()->addDay()]);
        $this->anotacao = Anotacao::create(['descricao' => 'Primeiro contato', 'usuario_id' => $this->lead->id]);
        $this->arquivo = arquivo::create(['nome' => 'rg.pdf', 'local' => 'arquivos/rg.pdf', 'usuario_id' => $this->lead->id]);
    }

    public function test_intruso_nao_alcanca_projeto_de_lead_alheio(): void
    {
        $this->actingAs($this->intruso)->getJson("/api/projeto/{$this->projeto->id}")->assertNotFound();
        $this->actingAs($this->dono)->getJson("/api/projeto/{$this->projeto->id}")->assertOk();
    }

    public function test_intruso_nao_apaga_tarefa_de_lead_alheio(): void
    {
        $this->actingAs($this->intruso)->deleteJson("/api/tarefas/{$this->tarefa->id}")->assertNotFound();
        $this->assertDatabaseHas('tarefas', ['id' => $this->tarefa->id, 'deleted_at' => null]);
    }

    public function test_intruso_nao_apaga_anotacao_de_lead_alheio(): void
    {
        $this->actingAs($this->intruso)->deleteJson("/api/anotacao/{$this->anotacao->id}")->assertNotFound();
    }

    public function test_intruso_nao_apaga_arquivo_de_lead_alheio(): void
    {
        $this->actingAs($this->intruso)->deleteJson("/api/arquivos/{$this->arquivo->id}")->assertNotFound();
    }

    /**
     * Review Focus 5: neste endpoint o `{id}` é o id do LEAD, não da tarefa.
     * Autorizar a entidade errada aqui abriria o buraco em vez de fechá-lo.
     */
    public function test_listar_tarefas_autoriza_o_lead_e_nao_a_tarefa(): void
    {
        $this->actingAs($this->intruso)->getJson("/api/tarefas/{$this->lead->id}")->assertNotFound();
        $this->actingAs($this->dono)->getJson("/api/tarefas/{$this->lead->id}")->assertOk();
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=UmSaltoPolicyTest`
Expected: FAIL — todos os `assertNotFound` recebem 200 ou 201.

- [ ] **Step 3: Escrever as quatro policies**

As quatro são o mesmo desenho. `app/Policies/ProjetoPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Projeto;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Um salto até o dono: projeto -> usuario.user_id.
 */
class ProjetoPolicy
{
    public function view(User $user, Projeto $projeto): Response
    {
        return $this->doDono($user, $projeto);
    }

    public function update(User $user, Projeto $projeto): Response
    {
        return $this->doDono($user, $projeto);
    }

    public function delete(User $user, Projeto $projeto): Response
    {
        return $this->doDono($user, $projeto);
    }

    private function doDono(User $user, Projeto $projeto): Response
    {
        if ($user->can('leads.view-all')) {
            return Response::allow();
        }

        return $projeto->usuario?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
```

Isso exige a relação inversa. Em `app/Models/Projeto.php`, acrescentar:

```php
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
```

`app/Policies/TarefaPolicy.php` — a relação `lead()` já existe no model:

```php
<?php

namespace App\Policies;

use App\Models\Tarefa;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Um salto até o dono: tarefa -> lead.user_id. */
class TarefaPolicy
{
    public function view(User $user, Tarefa $tarefa): Response
    {
        return $this->doDono($user, $tarefa);
    }

    public function update(User $user, Tarefa $tarefa): Response
    {
        return $this->doDono($user, $tarefa);
    }

    public function delete(User $user, Tarefa $tarefa): Response
    {
        return $this->doDono($user, $tarefa);
    }

    private function doDono(User $user, Tarefa $tarefa): Response
    {
        if ($user->can('leads.view-all')) {
            return Response::allow();
        }

        return $tarefa->lead?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
```

`app/Policies/AnotacaoPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\Anotacao;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Um salto até o dono: anotação -> usuario.user_id. */
class AnotacaoPolicy
{
    public function view(User $user, Anotacao $anotacao): Response
    {
        return $this->doDono($user, $anotacao);
    }

    public function update(User $user, Anotacao $anotacao): Response
    {
        return $this->doDono($user, $anotacao);
    }

    public function delete(User $user, Anotacao $anotacao): Response
    {
        return $this->doDono($user, $anotacao);
    }

    private function doDono(User $user, Anotacao $anotacao): Response
    {
        if ($user->can('leads.view-all')) {
            return Response::allow();
        }

        return $anotacao->usuario?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
```

`app/Policies/ArquivoPolicy.php` — atenção ao nome minúsculo do model:

```php
<?php

namespace App\Policies;

use App\Models\arquivo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Um salto até o dono: arquivo -> usuario.user_id. */
class ArquivoPolicy
{
    public function view(User $user, arquivo $arquivo): Response
    {
        return $this->doDono($user, $arquivo);
    }

    public function delete(User $user, arquivo $arquivo): Response
    {
        return $this->doDono($user, $arquivo);
    }

    private function doDono(User $user, arquivo $arquivo): Response
    {
        if ($user->can('leads.view-all')) {
            return Response::allow();
        }

        return $arquivo->usuario?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
```

E as relações que faltam. Em `app/Models/Anotacao.php` e em
`app/Models/arquivo.php`, acrescentar:

```php
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
```

- [ ] **Step 4: Registrar as quatro**

Em `AppServiceProvider::boot()`, abaixo do registro da Tarefa 2:

```php
        Gate::policy(Projeto::class, ProjetoPolicy::class);
        Gate::policy(Tarefa::class, TarefaPolicy::class);
        Gate::policy(Anotacao::class, AnotacaoPolicy::class);
        Gate::policy(arquivo::class, ArquivoPolicy::class);
```

- [ ] **Step 5: Converter rotas e autorizar**

Em `routes/api.php`:

```php
    Route::get('/anotacao/{usuario}', [Userarios::class, 'viewAnotacao']);
    Route::put('/anotacao/{anotacao}', [Userarios::class, 'updateAnotacao']);
    Route::delete('/anotacao/{anotacao}', [Userarios::class, 'destroyAnotacao']);

    Route::get('/projeto/{projeto}', [ProjetoController::class, 'viewProjeto']);
    Route::put('/projeto/{projeto}', [ProjetoController::class, 'update']);

    Route::get('/tarefas/{usuario}', [TarefaController::class, 'index']);
    Route::put('/tarefas/{tarefa}', [TarefaController::class, 'update']);
    Route::delete('/tarefas/{tarefa}', [TarefaController::class, 'destroy']);
```

Nos controllers, o padrão é sempre o mesmo: o model chega resolvido, o
`findOrFail` sai, o `catch (\Exception)` que virava 404 sai junto.

`TarefaController` — repare que `index` autoriza o **lead** e não a tarefa,
porque o parâmetro ali é o id do lead:

```php
    public function index(Usuario $usuario)
    {
        $this->authorize('view', $usuario);

        return response()->json(Tarefa::where('usuario_id', $usuario->id)->get());
    }

    public function update(Request $request, Tarefa $tarefa)
    {
        $this->authorize('update', $tarefa);

        $tarefa->update($request->all());

        return response()->json(['message' => 'Tarefa atualizada']);
    }

    public function destroy(Tarefa $tarefa)
    {
        $this->authorize('delete', $tarefa);

        $tarefa->delete();

        return response()->json(['message' => 'Tarefa removida']);
    }
```

`ProjetoController`:

```php
    public function viewProjeto(Projeto $projeto)
    {
        $this->authorize('view', $projeto);

        return response()->json($projeto->load('status'));
    }

    public function update(ProjetoRequest $request, Projeto $projeto)
    {
        $this->authorize('update', $projeto);

        app(AplicarTransicao::class)($projeto, $request->validated(), RegrasDePerda::extrair($request));

        return response()->json(['message' => 'Projeto atualizado com sucesso'], 200);
    }
```

`Userarios` — as três de anotação. `viewAnotacao` recebe o **lead**:

```php
    public function viewAnotacao(Usuario $usuario)
    {
        $this->authorize('view', $usuario);

        return response()->json(Anotacao::where('usuario_id', $usuario->id)->get());
    }

    public function updateAnotacao(Request $request, Anotacao $anotacao)
    {
        $this->authorize('update', $anotacao);

        $anotacao->update($request->validate(['descricao' => 'required|string']));

        return response()->json(['message' => 'Anotação atualizada']);
    }

    public function destroyAnotacao(Anotacao $anotacao)
    {
        $this->authorize('delete', $anotacao);

        $anotacao->delete();

        return response()->json(['message' => 'Anotação removida']);
    }
```

`app/Http/Controllers/arquivo.php`:

```php
    public function destroy(ArquivoModel $arquivo)
    {
        $this->authorize('delete', $arquivo);

        $arquivo->delete();

        return response()->json(['message' => 'Arquivo removido']);
    }
```

- [ ] **Step 6: Rodar os testes**

Run: `php artisan test --filter=UmSaltoPolicyTest && php artisan test`
Expected: PASS nos dois.

- [ ] **Step 7: Commit**

```bash
git add app/Policies app/Models app/Providers/AppServiceProvider.php routes/api.php app/Http/Controllers tests/Feature/Autorizacao/UmSaltoPolicyTest.php
git commit -m "feat: policies de um salto para projeto, tarefa, anotacao e arquivo"
```

---

### Task 4: Policies de dois saltos e a armadilha do `{id}` por verbo

**Files:**
- Create: `app/Policies/ProjetoAnotacaoPolicy.php`, `app/Policies/ProjetoAnexoPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`, `routes/api.php`, `app/Http/Controllers/ProjetoController.php`
- Test: `tests/Feature/Autorizacao/DoisSaltosPolicyTest.php`

**Interfaces:**
- Consumes: o formato das Tarefas 2 e 3.
- Produces: duas policies resolvendo `→ projeto.usuario.user_id`.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Autorizacao;

use App\Models\Funil;
use App\Models\Projeto;
use App\Models\ProjetoAnexo;
use App\Models\ProjetoAnotacao;
use App\Models\Statu;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Dois saltos: anexo/anotação -> projeto -> usuario.user_id.
 *
 * `/projetoAnotacao/{id}` é a armadilha documentada no spec: no GET o id é do
 * PROJETO, no PUT e no DELETE é da própria anotação. O binding resolve pelo
 * nome do parâmetro, então cada verbo declara o seu e a URL não muda.
 */
class DoisSaltosPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $dono;
    private User $intruso;
    private Projeto $projeto;
    private ProjetoAnotacao $anotacao;
    private ProjetoAnexo $anexo;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $this->dono = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->intruso = User::factory()->create(['tenant_id' => $tenant->id]);

        app(CurrentTenant::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
        $this->dono->assignRole('vendedor');
        $this->intruso->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $funil = Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $lead = Usuario::factory()->create([
            'tenant_id' => $tenant->id, 'user_id' => $this->dono->id, 'funil_id' => $funil->id,
        ]);
        $status = Statu::factory()->create(['tenant_id' => $tenant->id]);

        $this->projeto = new Projeto();
        $this->projeto->fill(['nome' => 'Proposta', 'usuario_id' => $lead->id, 'status_id' => $status->id]);
        $this->projeto->tenant_id = $tenant->id;
        $this->projeto->save();

        $this->anotacao = ProjetoAnotacao::create(['descricao' => 'Escopo', 'projeto_id' => $this->projeto->id]);
        $this->anexo = ProjetoAnexo::create(['nome' => 'contrato.pdf', 'local' => 'a/c.pdf', 'projeto_id' => $this->projeto->id]);
    }

    /** Review Focus 5: no GET o id é do projeto. */
    public function test_get_de_anotacao_autoriza_o_projeto(): void
    {
        $this->actingAs($this->intruso)->getJson("/api/projetoAnotacao/{$this->projeto->id}")->assertNotFound();
        $this->actingAs($this->dono)->getJson("/api/projetoAnotacao/{$this->projeto->id}")->assertOk();
    }

    /** No PUT e no DELETE o id é da própria anotação — mesma URL, outro model. */
    public function test_delete_de_anotacao_autoriza_a_anotacao(): void
    {
        $this->actingAs($this->intruso)->deleteJson("/api/projetoAnotacao/{$this->anotacao->id}")->assertNotFound();
        $this->assertDatabaseHas('projetoAnotacaos', ['id' => $this->anotacao->id, 'deleted_at' => null]);
    }

    public function test_delete_de_anexo_autoriza_o_anexo(): void
    {
        $this->actingAs($this->intruso)->deleteJson("/api/projetoAnexo/{$this->anexo->id}")->assertNotFound();
    }

    /** Review Focus 4: linha soft-deleted some pelo binding, e isso é o desejado. */
    public function test_anotacao_apagada_responde_404_para_o_proprio_dono(): void
    {
        $this->anotacao->delete();

        $this->actingAs($this->dono)
            ->deleteJson("/api/projetoAnotacao/{$this->anotacao->id}")
            ->assertNotFound();
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=DoisSaltosPolicyTest`
Expected: FAIL nos `assertNotFound` de intruso.

- [ ] **Step 3: Escrever as duas policies**

`app/Policies/ProjetoAnotacaoPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\ProjetoAnotacao;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Dois saltos: anotação -> projeto -> usuario.user_id.
 */
class ProjetoAnotacaoPolicy
{
    public function update(User $user, ProjetoAnotacao $anotacao): Response
    {
        return $this->doDono($user, $anotacao);
    }

    public function delete(User $user, ProjetoAnotacao $anotacao): Response
    {
        return $this->doDono($user, $anotacao);
    }

    private function doDono(User $user, ProjetoAnotacao $anotacao): Response
    {
        if ($user->can('leads.view-all')) {
            return Response::allow();
        }

        return $anotacao->projeto?->usuario?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
```

`app/Policies/ProjetoAnexoPolicy.php`:

```php
<?php

namespace App\Policies;

use App\Models\ProjetoAnexo;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Dois saltos: anexo -> projeto -> usuario.user_id. */
class ProjetoAnexoPolicy
{
    public function delete(User $user, ProjetoAnexo $anexo): Response
    {
        if ($user->can('leads.view-all')) {
            return Response::allow();
        }

        return $anexo->projeto?->usuario?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
```

- [ ] **Step 4: Registrar e converter as rotas**

Em `AppServiceProvider::boot()`:

```php
        Gate::policy(ProjetoAnotacao::class, ProjetoAnotacaoPolicy::class);
        Gate::policy(ProjetoAnexo::class, ProjetoAnexoPolicy::class);
```

Em `routes/api.php` — cada verbo declara o próprio nome de parâmetro, e
nenhuma URL muda:

```php
    Route::get('/projetoAnotacao/{projeto}',            [ProjetoController::class, 'viewAnotacao']);
    Route::put('/projetoAnotacao/{projetoAnotacao}',    [ProjetoController::class, 'updateAnotacao']);
    Route::delete('/projetoAnotacao/{projetoAnotacao}', [ProjetoController::class, 'destroyAnotacao']);

    Route::get('/projetoAnexo/{projeto}',          [ProjetoController::class, 'viewAnexo']);
    Route::delete('/projetoAnexo/{projetoAnexo}',  [ProjetoController::class, 'destroyAnexo']);
```

Nos métodos do controller, trocar `$id` pelos models e autorizar: `viewAnotacao`
e `viewAnexo` autorizam `view` no **projeto**; `updateAnotacao`,
`destroyAnotacao` e `destroyAnexo` autorizam na **própria linha**.

- [ ] **Step 5: Rodar os testes**

Run: `php artisan test --filter=DoisSaltosPolicyTest && php artisan test`
Expected: PASS nos dois.

- [ ] **Step 6: Commit**

```bash
git add app/Policies app/Providers/AppServiceProvider.php routes/api.php app/Http/Controllers/ProjetoController.php tests/Feature/Autorizacao/DoisSaltosPolicyTest.php
git commit -m "feat: policies de dois saltos para anotacao e anexo de projeto"
```

---

### Task 5: `TarefaPadraoPolicy`

**Files:**
- Create: `app/Policies/TarefaPadraoPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`, `routes/api.php`, `app/Http/Controllers/TarefaPadraoController.php`
- Test: `tests/Feature/Autorizacao/TarefaPadraoPolicyTest.php`

**Interfaces:**
- Consumes: o formato das tarefas anteriores.
- Produces: a oitava e última policy. O dono é direto, em `tarefa_padroes.user_id`.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Autorizacao;

use App\Models\TarefaPadrao;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Modelo de tarefa tem dono direto: `tarefa_padroes.user_id`.
 */
class TarefaPadraoPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_intruso_nao_edita_modelo_de_colega(): void
    {
        $tenant = Tenant::factory()->create();
        $dono = User::factory()->create(['tenant_id' => $tenant->id]);
        $intruso = User::factory()->create(['tenant_id' => $tenant->id]);

        app(CurrentTenant::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
        $dono->assignRole('vendedor');
        $intruso->assignRole('vendedor');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $modelo = new TarefaPadrao();
        $modelo->fill(['user_id' => $dono->id, 'titulo' => 'Follow-up', 'prazo_dias' => 3]);
        $modelo->tenant_id = $tenant->id;
        $modelo->save();

        $this->actingAs($intruso)
            ->putJson("/api/tarefa-padroes/{$modelo->id}", ['titulo' => 'Sequestrado', 'prazo_dias' => 1])
            ->assertNotFound();

        $this->assertSame('Follow-up', $modelo->fresh()->titulo);

        $this->actingAs($dono)
            ->putJson("/api/tarefa-padroes/{$modelo->id}", ['titulo' => 'Follow-up 2', 'prazo_dias' => 1])
            ->assertOk();
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=TarefaPadraoPolicyTest`
Expected: FAIL — o intruso recebe 200.

- [ ] **Step 3: Escrever a policy**

```php
<?php

namespace App\Policies;

use App\Models\TarefaPadrao;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Dono direto, em `tarefa_padroes.user_id`.
 *
 * Aqui `leads.view-all` NÃO passa: modelo de tarefa é ferramenta pessoal do
 * agente, não dado de carteira. Enxergar a carteira da equipe não é motivo
 * para editar o modelo de trabalho de um colega.
 */
class TarefaPadraoPolicy
{
    public function update(User $user, TarefaPadrao $modelo): Response
    {
        return $modelo->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    public function delete(User $user, TarefaPadrao $modelo): Response
    {
        return $this->update($user, $modelo);
    }
}
```

- [ ] **Step 4: Registrar, converter rota e autorizar**

```php
        Gate::policy(TarefaPadrao::class, TarefaPadraoPolicy::class);
```

```php
    Route::put('/tarefa-padroes/{tarefaPadrao}',    [TarefaPadraoController::class, 'update']);
    Route::delete('/tarefa-padroes/{tarefaPadrao}', [TarefaPadraoController::class, 'destroy']);
```

E nos dois métodos do controller, trocar `$id` por `TarefaPadrao $tarefaPadrao`
e chamar `$this->authorize('update', $tarefaPadrao)` / `'delete'`.

- [ ] **Step 5: Rodar os testes**

Run: `php artisan test --filter=TarefaPadraoPolicyTest && php artisan test`
Expected: PASS nos dois.

- [ ] **Step 6: Commit**

```bash
git add app/Policies/TarefaPadraoPolicy.php app/Providers/AppServiceProvider.php routes/api.php app/Http/Controllers/TarefaPadraoController.php tests/Feature/Autorizacao/TarefaPadraoPolicyTest.php
git commit -m "feat: TarefaPadraoPolicy protege modelo de tarefa pessoal"
```

---

### Task 6: FormRequests — `authorize()` real e a separação de `ArquivoRequest`

**Files:**
- Modify: `app/Http/Requests/UsuarioRequest.php`, `app/Http/Requests/ProjetoRequest.php`, `app/Http/Requests/ArquivoRequest.php`
- Create: `app/Http/Requests/ProjetoAnexoRequest.php`
- Modify: `app/Http/Controllers/ProjetoController.php`
- Test: `tests/Feature/Autorizacao/FormRequestsTest.php`

**Interfaces:**
- Consumes: as permissions confirmadas nas Global Constraints.
- Produces: `ProjetoAnexoRequest`, consumido por `ProjetoController::createAnexo`.

- [ ] **Step 1: Escrever o teste que falha**

```php
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
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * `ArquivoRequest` servia dois endpoints incompatíveis: em `arquivo::store` o
 * campo `usuario_id` é id de LEAD; em `ProjetoController::createAnexo` o mesmo
 * campo carrega um id de PROJETO. Só funcionava porque a regra era `required`
 * e nada mais — adicionar `exists:usuarios` quebraria o anexo de projeto.
 */
class FormRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_anexo_de_lead_recusa_id_que_nao_e_lead(): void
    {
        [$staff, $lead] = $this->cenario();

        $this->actingAs($staff)->postJson('/api/arquivos', [
            'usuario_id' => 999999,
            'arquivo' => UploadedFile::fake()->create('a.pdf', 10),
        ])->assertStatus(422);
    }

    public function test_anexo_de_projeto_continua_aceitando_id_de_projeto(): void
    {
        [$staff, $lead, $projeto] = $this->cenario();

        $this->actingAs($staff)->postJson('/api/projetoAnexo', [
            'usuario_id' => $projeto->id,
            'arquivo' => UploadedFile::fake()->create('c.pdf', 10),
        ])->assertSuccessful();
    }

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

        return [$staff, $lead, $projeto];
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=FormRequestsTest`
Expected: FAIL em `test_anexo_de_lead_recusa_id_que_nao_e_lead` — hoje passa qualquer inteiro.

- [ ] **Step 3: `authorize()` real nos três requests**

Em `UsuarioRequest`:

```php
    public function authorize(): bool
    {
        return $this->user()?->can('leads.manage') ?? false;
    }
```

Em `ProjetoRequest`:

```php
    public function authorize(): bool
    {
        return $this->user()?->can('projetos.manage') ?? false;
    }
```

Em `ArquivoRequest`, o mesmo de `UsuarioRequest` (anexo de lead é dado de lead).

- [ ] **Step 4: Apertar `ArquivoRequest` e criar `ProjetoAnexoRequest`**

`ArquivoRequest::rules()` passa a ser:

```php
    public function rules(): array
    {
        return [
            'arquivo' => ['required', 'file'],
            'usuario_id' => [
                'required',
                Rule::exists('usuarios', 'id')->where('tenant_id', app(CurrentTenant::class)->id()),
            ],
        ];
    }
```

com os `use` de `Rule` e `CurrentTenant`.

`app/Http/Requests/ProjetoAnexoRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Anexo de PROJETO.
 *
 * O campo se chama `usuario_id` e carrega um id de projeto — o controller faz
 * `'projeto_id' => $request->usuario_id` e o frontend envia
 * `fd.append('usuario_id', projetoId)`. O nome está errado e é conhecido;
 * renomear exige mexer no frontend e fica como tarefa própria. O que esta
 * classe corrige é a validação, que antes aceitava qualquer inteiro.
 */
class ProjetoAnexoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('projetos.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'arquivo' => ['required', 'file'],
            'usuario_id' => [
                'required',
                Rule::exists('projetos', 'id')->where('tenant_id', app(CurrentTenant::class)->id()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'usuario_id.required' => 'O projeto do anexo é obrigatório.',
            'usuario_id.exists' => 'Projeto inválido.',
            'arquivo.required' => 'O campo arquivo é obrigatório.',
            'arquivo.file' => 'Deve ser enviado um arquivo.',
        ];
    }
}
```

Em `ProjetoController::createAnexo`, trocar o type hint de `ArquivoRequest` para
`ProjetoAnexoRequest`.

- [ ] **Step 5: Rodar os testes**

Run: `php artisan test --filter=FormRequestsTest && php artisan test`
Expected: PASS nos dois.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests app/Http/Controllers/ProjetoController.php tests/Feature/Autorizacao/FormRequestsTest.php
git commit -m "feat: authorize() real nos FormRequests e separacao de ArquivoRequest"
```

---

### Task 7: Rede de segurança — toda rota de escrita autoriza, ou justifica

**Reescrita em 27/09, durante a execução.** A versão original desta tarefa
procurava parâmetros `{id}` soltos nas rotas. Ela **não teria pego nenhuma** das
oito falhas que a execução encontrou, porque todas entram por um campo do
**corpo** da requisição, onde não existe parâmetro de rota para inspecionar.

Uma rede que não pega a falha que acabou de acontecer oito vezes não é rede.

**Files:**
- Test: `tests/Feature/Autorizacao/TodaRotaDeEscritaAutorizaTest.php`

**Interfaces:**
- Consumes: todas as policies e requests das Tarefas 2 a 6 e 8.
- Produces: nada. É a rede.

- [ ] **Step 1: Escrever o teste**

```php
<?php

namespace Tests\Feature\Autorizacao;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Toda rota de escrita sob /api autoriza explicitamente, ou está isenta com
 * motivo escrito.
 *
 * Por que esta forma, e não a inspeção de parâmetros de rota: as oito falhas
 * de autorização que este plano fechou entram por um campo do CORPO da
 * requisição — `usuario_id`, `projeto_id`, `user_id`. O route model binding só
 * enxerga a URL, então nenhuma delas apareceria numa varredura de parâmetros.
 * A pergunta que pega essa classe é outra: "este método decide alguma coisa
 * sem nunca perguntar se pode?"
 *
 * Um método passa se fizer uma destas duas coisas:
 *   - chamar `$this->authorize(...)` no próprio corpo; ou
 *   - receber um FormRequest cujo `authorize()` faça algo além de `return true`.
 *
 * Qualquer outra rota de escrita precisa estar em ISENTAS, com o motivo ao
 * lado. A isenção é barata de escrever e cara de justificar — é esse o ponto.
 */
class TodaRotaDeEscritaAutorizaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rotas de escrita que legitimamente não autorizam por instância.
     * Cada linha precisa do motivo, e o motivo precisa sobreviver a uma
     * pergunta: "o que um agente mal-intencionado faria com isto?"
     */
    private const ISENTAS = [
        // Configuração do tenant: barradas por can:configuracoes.manage no
        // grupo de rotas. Não existe "meu funil" e "funil do colega" — existe
        // o funil da empresa.
        'POST api/funis',
        'PUT api/funis/{funil}',
        'DELETE api/funis/{funil}',
        'PATCH api/funis/reordenar',
        'PATCH api/funis/{funil}/padrao',
        'POST api/funis/{funil}/estagios',
        'PATCH api/funis/{funil}/estagios/reordenar',
        'PUT api/estagios/{estagio}',
        'DELETE api/estagios/{estagio}',
        'PATCH api/estagios/{estagio}/restaurar',
        'POST api/motivos-perda',
        'PUT api/motivos-perda/{motivo}',
        'DELETE api/motivos-perda/{motivo}',
        'PATCH api/motivos-perda/{motivo}/restaurar',
        'PATCH api/motivos-perda/reordenar',

        // Escrevem apenas no próprio usuário autenticado; não há recurso de
        // terceiro a proteger.
        'PATCH api/kanban/settings',

        // Leitura disfarçada de POST por convenção antiga do projeto: o corpo
        // carrega filtros, não identificador de dono. A visibilidade é
        // garantida por Usuario::scopeVisibleTo dentro do método.
        'POST api/pegarUsuarios',
        'POST api/kanban',
        'POST api/metricas',
        'POST api/tarefasPendentes',
        'POST api/estagios',
        'POST api/status',

        // Cria modelo de tarefa do próprio agente: user_id vem de auth(),
        // nunca do corpo.
        'POST api/tarefa-padroes',

        // Cria lead novo: não existe dono anterior a respeitar. A permissão de
        // classe é checada por UsuarioRequest::authorize().
        'POST api/usuarios',
    ];

    public function test_toda_rota_de_escrita_autoriza_ou_esta_isenta(): void
    {
        $desprotegidas = [];

        foreach (Route::getRoutes() as $rota) {
            if (! str_starts_with($rota->uri(), 'api/')) {
                continue;
            }

            $metodos = array_intersect($rota->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']);
            if (! $metodos) {
                continue;
            }

            $assinatura = reset($metodos).' '.$rota->uri();
            if (in_array($assinatura, self::ISENTAS, true)) {
                continue;
            }

            $acao = $rota->getAction('uses');
            if (! is_string($acao) || ! str_contains($acao, '@')) {
                continue;
            }

            [$classe, $metodo] = explode('@', $acao);
            if (! class_exists($classe) || ! method_exists($classe, $metodo)) {
                continue;
            }

            if (! $this->autoriza(new ReflectionMethod($classe, $metodo))) {
                $desprotegidas[] = $assinatura.'  ->  '.class_basename($classe).'::'.$metodo;
            }
        }

        $this->assertSame([], $desprotegidas, implode("\n  ", array_merge(
            ['rotas de escrita sem autorizacao e sem isencao justificada:'],
            $desprotegidas,
        )));
    }

    /**
     * O método autoriza se chamar authorize() no corpo, ou se receber um
     * FormRequest que autorize de verdade.
     */
    private function autoriza(ReflectionMethod $metodo): bool
    {
        if (str_contains($this->corpoDe($metodo), '$this->authorize(')) {
            return true;
        }

        foreach ($metodo->getParameters() as $parametro) {
            $tipo = $parametro->getType();
            if (! $tipo || $tipo->isBuiltin()) {
                continue;
            }

            $classe = $tipo->getName();
            if (! is_subclass_of($classe, \Illuminate\Foundation\Http\FormRequest::class)) {
                continue;
            }

            if ($this->formRequestAutoriza($classe)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Um `authorize()` que só faz `return true;` não autoriza nada — é o valor
     * padrão do scaffold. Era exatamente o que três FormRequests deste projeto
     * tinham antes deste plano.
     */
    private function formRequestAutoriza(string $classe): bool
    {
        if (! method_exists($classe, 'authorize')) {
            return false;
        }

        $corpo = $this->corpoDe(new ReflectionMethod($classe, 'authorize'));

        return ! preg_match('/^\s*\{?\s*return\s+true\s*;\s*\}?\s*$/', $corpo);
    }

    private function corpoDe(ReflectionMethod $metodo): string
    {
        $arquivo = $metodo->getFileName();
        if ($arquivo === false) {
            return '';
        }

        $linhas = file($arquivo);
        $inicio = $metodo->getStartLine();
        $fim = $metodo->getEndLine();

        return implode('', array_slice($linhas, $inicio, $fim - $inicio));
    }
}
```

- [ ] **Step 2: Rodar**

Run: `php artisan test --filter=TodaRotaDeEscritaAutorizaTest`
Expected: PASS. Se falhar, a mensagem nomeia cada rota desprotegida — trate
cada uma: ou ela precisa autorizar, ou precisa entrar em `ISENTAS` com motivo
escrito. **Não acrescente à lista de isenção sem escrever o porquê.**

- [ ] **Step 3: Provar que a rede pega o que deveria pegar**

Uma rede nunca testada contra uma falha real é decoração. Remova
temporariamente a linha `$this->authorize('update', $lead);` de
`TarefaController::store`, rode o teste, confirme que ele FALHA nomeando
`POST api/tarefas`, e então restaure a linha e rode de novo para ver passar.
Registre no relatório os dois resultados.

- [ ] **Step 4: Rodar a suíte inteira**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 5: Commit** — PULAR, conforme a Global Constraint deste plano.

### Task 8: As portas que recebem o dono por um campo do corpo

**Acrescentada em 27/09, durante a execução.** A revisão da Tarefa 6 provou em
runtime um buraco que o spec não previa, e o grep que se seguiu encontrou mais
três endpoints da mesma forma.

**A premissa errada do spec.** Ele diz que "a autorização de instância fica nas
policies (o controller já terá o model resolvido)". Isso só vale para PUT e
DELETE, onde o route model binding resolve o recurso a partir da URL. Num POST
que referencia outro recurso por id **no corpo**, o binding nunca vê esse id —
e nenhuma policy roda sozinha.

**Files:**
- Modify: `app/Http/Controllers/ProjetoController.php` (`view`, `createAnotacao`)
- Modify: `app/Http/Controllers/Userarios.php` (`createAnotacao`)
- Test: `tests/Feature/Autorizacao/DonoNoCorpoTest.php`

**Interfaces:**
- Consumes: `UsuarioPolicy` e `ProjetoPolicy` das Tarefas 2 e 3.
- Produces: nada. É fechamento de buraco.

- [ ] **Step 1: Escrever o teste que falha**

```php
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
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Endpoints que recebem o id do dono por um campo do CORPO.
 *
 * O route model binding só enxerga parâmetros de URL, então nestes três
 * caminhos nenhuma policy rodava sozinha: bastava enviar o id do colega no
 * corpo. É a mesma classe de IDOR que o resto do plano fecha, por outra porta.
 */
class DonoNoCorpoTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $dono;
    private User $colega;
    private Usuario $lead;
    private Projeto $projeto;

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
        $this->lead = Usuario::factory()->create([
            'tenant_id' => $this->tenant->id, 'user_id' => $this->dono->id, 'funil_id' => $funil->id,
        ]);

        $status = Statu::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->projeto = new Projeto();
        $this->projeto->fill(['nome' => 'Proposta', 'usuario_id' => $this->lead->id, 'status_id' => $status->id]);
        $this->projeto->tenant_id = $this->tenant->id;
        $this->projeto->save();
    }

    public function test_listar_projetos_exige_posse_do_lead(): void
    {
        $this->actingAs($this->colega)
            ->postJson('/api/projetos', ['usuario_id' => $this->lead->id])
            ->assertNotFound();

        $this->actingAs($this->dono)
            ->postJson('/api/projetos', ['usuario_id' => $this->lead->id])
            ->assertOk();
    }

    /**
     * A pior das três: `usuario_id` só tinha `required` — nem existência, nem
     * tenant, nem posse.
     */
    public function test_anotar_em_lead_de_colega_e_recusado(): void
    {
        $this->actingAs($this->colega)
            ->postJson('/api/anotacao', ['descricao' => 'Intrusa', 'usuario_id' => $this->lead->id])
            ->assertNotFound();

        $this->assertDatabaseMissing('anotacaos', ['descricao' => 'Intrusa']);
    }

    public function test_anotar_em_projeto_de_colega_e_recusado(): void
    {
        $this->actingAs($this->colega)
            ->postJson('/api/projetoAnotacao', ['descricao' => 'Intrusa', 'projeto_id' => $this->projeto->id])
            ->assertNotFound();

        $this->assertDatabaseMissing('projetoAnotacaos', ['descricao' => 'Intrusa']);
    }

    /**
     * Id inexistente e id de terceiro precisam dar a MESMA resposta, senão a
     * diferença revela quais ids existem no tenant.
     */
    public function test_id_inexistente_e_id_alheio_respondem_igual(): void
    {
        $alheio = $this->actingAs($this->colega)
            ->postJson('/api/anotacao', ['descricao' => 'A', 'usuario_id' => $this->lead->id])
            ->status();

        $inexistente = $this->actingAs($this->colega)
            ->postJson('/api/anotacao', ['descricao' => 'A', 'usuario_id' => 999999])
            ->status();

        $this->assertSame($alheio, $inexistente, 'a diferença de status revelaria quais ids existem');
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=DonoNoCorpoTest`
Expected: FAIL — os três `assertNotFound` recebem 200 ou 201.

- [ ] **Step 3: Autorizar o lead em `ProjetoController::view`**

```php
    public function view(Request $request)
    {
        // O lead vem de um campo do CORPO: o binding nunca o enxerga, então a
        // policy não roda sozinha. findOrFail primeiro — o TenantScope já
        // devolve 404 para id de outro tenant — e só então autoriza, para que
        // "não existe" e "não é seu" deem a mesma resposta.
        $lead = Usuario::findOrFail($request->integer('usuario_id'));

        $this->authorize('view', $lead);

        return response()->json(
            Projeto::where('usuario_id', $lead->id)->with('status')->get()
        );
    }
```

Acrescente `use App\Models\Usuario;` se faltar.

- [ ] **Step 4: Autorizar o lead em `Userarios::createAnotacao`**

```php
    public function createAnotacao(Request $request)
    {
        try {
            $lead = Usuario::findOrFail($request->integer('usuario_id'));

            $this->authorize('update', $lead);

            $validated = $request->validate(['descricao' => 'required|string']);

            Anotacao::create([
                'descricao' => $validated['descricao'],
                'usuario_id' => $lead->id,
            ]);

            return response()->json(['message' => 'Anotação cadastrada com sucesso'], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json(['erros' => $error->errors()], 422);
        }
    }
```

- [ ] **Step 5: Autorizar o projeto em `ProjetoController::createAnotacao`**

```php
    public function createAnotacao(Request $request)
    {
        try {
            $projeto = Projeto::findOrFail($request->integer('projeto_id'));

            $this->authorize('update', $projeto);

            $validated = $request->validate(['descricao' => 'required|string']);

            ProjetoAnotacao::create([
                'descricao' => $validated['descricao'],
                'projeto_id' => $projeto->id,
            ]);

            return response()->json(['message' => 'Anotação cadastrada com sucesso'], 201);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return response()->json(['erros' => $error->errors()], 422);
        }
    }
```

- [ ] **Step 6: Rodar os testes**

Run: `php artisan test --filter=DonoNoCorpoTest && php artisan test`
Expected: PASS nos dois.

- [ ] **Step 7: Commit** — PULAR, conforme a Global Constraint deste plano.
