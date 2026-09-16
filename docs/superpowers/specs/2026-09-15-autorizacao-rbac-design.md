# Autorização e RBAC

Data: 2026-09-15
Branch: `update/atualizacaoDeFiltros`

## Problema

A multi-tenancy fechou o vazamento *entre* empresas. Dentro de uma mesma empresa
não existe autorização nenhuma: qualquer agente autenticado lê e altera os leads,
projetos, tarefas, anotações e arquivos de qualquer outro agente, bastando
adivinhar um ID.

Evidência no código atual, toda ela em rotas já protegidas por `auth` + `tenant`:

| Endpoint | Consulta | Falta |
|---|---|---|
| `GET /api/usuarioPerfil/{id}` | `Usuario::where('id', $id)` | filtro de dono |
| `GET /api/timeline/{id}` | `Usuario::findOrFail($id)` | filtro de dono |
| `PUT /api/usuarios/{id}` | `Usuario::findOrFail($id)` | filtro de dono |
| `DELETE /api/usuarios/{usuario}` | `Usuario::findOrFail($id)` | filtro de dono |
| `GET /api/tarefas/{usuarioId}` | `Tarefa::where('usuario_id', $usuarioId)` | filtro de dono |
| `PUT\|DELETE /api/tarefas/{id}` | `Tarefa::findOrFail($id)` | filtro de dono |
| `GET\|PUT /api/projeto/{id}` | `Projeto::where('id', $id)` | filtro de dono |
| `GET /api/projetoAnotacao/{id}` | `ProjetoAnotacao::where('projeto_id', $id)` | filtro de dono |
| `GET /api/projetoAnexo/{id}` | `ProjetoAnexo::where('projeto_id', $id)` | filtro de dono |
| `PUT\|DELETE /api/tarefa-padroes/{id}` | `TarefaPadrao::findOrFail($id)` | filtro de dono |
| `DELETE /api/arquivos/{arquivo}` | `ArquivoModel::findOrFail($id)` | filtro de dono |
| `POST /api/buscarArquivo` | `ArquivoModel::where('usuario_id', $request->user_id)` | dono vem do corpo |

São 22 rotas com parâmetro — 19 declaradas explicitamente mais 3 geradas pelo
`apiResource('arquivos')` — e 42 métodos de controller em 5 controllers.

Os três `FormRequest` de domínio declaram `authorize(): bool { return true; }` —
hoje eles só validam formato, não autorizam nada. `ArquivoRequest` ainda valida
`usuario_id` apenas como `required`, sem `exists` nem filtro de tenant, o que
permite anexar arquivo a um lead de outro tenant.

Este documento trata o trabalho como correção de segurança, não como feature de
produto. O RBAC é o mecanismo; fechar o IDOR interno é o objetivo.

## Decisões

| Decisão | Escolha | Motivo |
|---|---|---|
| Pacote | `spatie/laravel-permission` **^6.25** | A 8.x exige PHP `^8.3`; a plataforma é 8.2. A 6.x tem `teams` completo, então o desenho não muda. |
| Escopo por empresa | `teams => true`, `team_foreign_key => 'tenant_id'` | Nasce escopado. Retrofit de teams depois seria mais caro. |
| Definição de papéis | Papéis e permissions **globais** (`team_id = null`), semeados uma vez | No modo teams do spatie, papel global é atribuível a qualquer team e é único. O que é por tenant é a *atribuição*, em `model_has_roles.tenant_id`. Evita duplicar a definição por empresa. |
| Policies | **8**, incluindo `ProjetoAnotacao` e `ProjetoAnexo` | Sem elas, os 6 endpoints de anotação/anexo de projeto continuariam abertos. |
| Resposta a acesso negado | **404**, via `Response::denyAsNotFound()` | Não confirma que o ID existe no tenant, então não dá para enumerar a carteira dos colegas. Recurso nativo do Laravel 11 — sem tratamento de exceção paralelo. |
| Escopo desta fase | **Somente backend** | Fecha o buraco com testes primeiro. Gating visual da UI vira tarefa seguinte. |
| Rede de segurança | Teste tabelado sobre todas as rotas com parâmetro | Mesmo espírito do `TenantIsolationTest` que já roda no CI. Quebra quando alguém adicionar rota sem policy. |

## Arquitetura

### 1. Pacote e configuração

- `composer require spatie/laravel-permission:^6.25`
- Publicar config e migrations do pacote.
- `config/permission.php`: `'teams' => true`, `'team_foreign_key' => 'tenant_id'`.
- `User` passa a usar a trait `HasRoles`.

As migrations do pacote criam `roles`, `permissions`, `model_has_roles`,
`model_has_permissions`, `role_has_permissions`, com `tenant_id` nas tabelas de
atribuição.

### 2. Papéis e permissões

Semeados **uma vez**, por migration, com `team_id = null` (globais):

Papéis: `admin`, `gestor`, `vendedor`.

Permissions: `leads.view-all`, `leads.manage`, `projetos.manage`,
`tarefas.manage`, `configuracoes.manage`, `agentes.manage`.

Mapeamento proposto — **não estava no pedido original, confirmar**:

| Permission | admin | gestor | vendedor |
|---|:---:|:---:|:---:|
| `leads.view-all` | ✓ | ✓ | |
| `leads.manage` | ✓ | ✓ | ✓ |
| `projetos.manage` | ✓ | ✓ | ✓ |
| `tarefas.manage` | ✓ | ✓ | ✓ |
| `configuracoes.manage` | ✓ | ✓ | |
| `agentes.manage` | ✓ | | |

A distinção que carrega o sistema é `leads.view-all`: ela separa quem enxerga a
carteira inteira do tenant de quem enxerga só a própria.

### 3. Tenant ativo e team do spatie

`IdentifyTenant` passa a fazer, logo após `CurrentTenant::set($tenant)`:

```php
setPermissionsTeamId($tenant->id);
auth()->user()->unsetRelation('roles')->unsetRelation('permissions');
```

O `unsetRelation` é exigido pela documentação do pacote: sem ele, as relações de
papel resolvidas antes da troca de team permanecem em cache no model e as
verificações leem o team errado.

### 4. Visibilidade de leads

`Usuario::scopeVisibleTo($query, User $user)`:

- com `leads.view-all` → sem filtro adicional (o `TenantScope` já limita ao tenant);
- sem → `where('user_id', $user->id)`.

Aplicado nos quatro endpoints de listagem: `Userarios::view`, `Userarios::kanban`,
`Userarios::metricas` e `TarefaController::pendentes`. Estes hoje já filtram por
`auth()->id()` fixo; o scope substitui esse filtro, abrindo a visão para gestor e
admin sem abrir para vendedor.

### 5. Policies

Oito policies em `app/Policies`, registradas em `AppServiceProvider::boot()`.

O que as diferencia é a profundidade da cadeia até o dono:

| Profundidade | Models | Caminho |
|---|---|---|
| Direto | `Usuario`, `TarefaPadrao` | `user_id` |
| 1 salto | `Projeto`, `Tarefa`, `Anotacao`, `Arquivo` | `→ usuario.user_id` |
| 2 saltos | `ProjetoAnotacao`, `ProjetoAnexo` | `→ projeto.usuario.user_id` |

Regra comum a todas, em uma frase: **quem tem `leads.view-all` passa dentro do
tenant; quem não tem, passa só no que é seu.** O `TenantScope` já garante que
nada de outro tenant chega até a policy.

Para os models de 1 e 2 saltos, a policy resolve a cadeia via relação Eloquent, e
a comparação final é sempre contra `usuarios.user_id`.

Negativas usam `Response::denyAsNotFound()` para produzir 404.

### 6. Route model binding

As 22 rotas com parâmetro passam a usar binding implícito: `{usuario}`, `{projeto}`,
`{tarefa}`, `{anotacao}`, `{arquivo}`, `{tarefaPadrao}`, `{projetoAnotacao}`,
`{projetoAnexo}`. Os controllers recebem o model já resolvido e chamam
`$this->authorize('update', $usuario)`.

Efeito colateral favorável: com o `TenantScope` ativo, o binding sozinho já
devolve 404 para ID de outro tenant, sem código adicional.

**O parâmetro não significa a mesma coisa em todas as rotas.** Levantamento feito
método a método:

| Rota | O que `{id}` é hoje | Vira |
|---|---|---|
| `GET /api/tarefas/{usuarioId}` | id do **lead** | `{usuario}`, autoriza `view` no lead |
| `GET /api/anotacao/{id}` | id do **lead** | `{usuario}`, autoriza `view` no lead |
| `GET /api/projetoAnotacao/{id}` | id do **projeto** | `{projeto}`, autoriza `view` no projeto |
| `GET /api/projetoAnexo/{id}` | id do **projeto** | `{projeto}`, autoriza `view` no projeto |
| `PUT\|DELETE /api/projetoAnotacao/{id}` | id da **anotação** | `{projetoAnotacao}` |
| `DELETE /api/projetoAnexo/{id}` | id do **anexo** | `{projetoAnexo}` |

As duas últimas linhas são a armadilha: `/projetoAnotacao/{id}` e
`/projetoAnexo/{id}` são o mesmo caminho com significados diferentes conforme o
verbo — id do projeto no `GET`, id do próprio recurso no `PUT`/`DELETE`. Alguém
que trocasse `{id}` por um nome só, no caminho inteiro, ligaria o model errado no
`GET` e devolveria 404 em anotação que existe.

Não é preciso mudar URL para resolver. O binding implícito resolve pelo **nome do
parâmetro**, e cada verbo declara o seu, mesmo compartilhando o caminho:

```php
Route::get('/projetoAnotacao/{projeto}',            [ProjetoController::class, 'viewAnotacao']);
Route::put('/projetoAnotacao/{projetoAnotacao}',    [ProjetoController::class, 'updateAnotacao']);
Route::delete('/projetoAnotacao/{projetoAnotacao}', [ProjetoController::class, 'destroyAnotacao']);
```

Nenhuma URL consumida pelo frontend muda.

### 7. FormRequests

- `UsuarioRequest`, `ProjetoRequest`, `ArquivoRequest`: `authorize()` deixa de ser
  `return true`. A autorização de instância fica nas policies (o controller já
  terá o model resolvido); o `authorize()` do request cobre a permissão de classe
  (ex.: `leads.manage`).

**`ArquivoRequest` está sendo usado por dois endpoints incompatíveis.** Ele valida
um campo `usuario_id`, e:

- em `arquivo::store` (anexo de lead) esse campo é de fato um id de lead;
- em `ProjetoController::createAnexo` (anexo de projeto) o campo carrega um **id de
  projeto** — o controller faz `'projeto_id' => $request->usuario_id`, e o
  frontend envia `fd.append('usuario_id', projetoId)`.

Funciona hoje só porque a regra é `required` e nada mais. Portanto **adicionar
`exists:usuarios` a `ArquivoRequest` quebraria o upload de anexo de projeto** — é
um conserto aparentemente óbvio que precisa ser evitado.

Correção: separar em dois requests.

- `ArquivoRequest` valida `usuario_id` com `Rule::exists('usuarios','id')->where('tenant_id', ...)`.
- `ProjetoAnexoRequest`, novo, valida contra `projetos` no mesmo padrão.

O nome do campo enviado pelo frontend permanece `usuario_id` nesta fase, com
comentário registrando o equívoco, para manter a promessa de "somente backend". A
renomeação para `projeto_id` fica como tarefa seguinte, junto com o gating visual.

### 8. Migração de dados

Migration de backfill atribui `admin` a todos os usuários do tenant 1, com
`setPermissionsTeamId(1)` antes da atribuição.

`TenantBootstrapper` passa a atribuir `admin` ao usuário que registra um tenant
novo. Ele **não** cria papéis — eles são globais e já existem.

## Tratamento de erros

| Situação | Resposta |
|---|---|
| Sem autenticação | 401 (já existe, via `IdentifyTenant`) |
| Usuário sem tenant, ou tenant soft-deleted | 403 (já existe) |
| Recurso de outro tenant | 404 (route model binding + `TenantScope`) |
| Recurso de outro agente, sem `leads.view-all` | 404 (`denyAsNotFound`) |
| Permissão de classe ausente (ex.: `configuracoes.manage`) | 403 |

A assimetria é deliberada: 404 protege a *existência* de um recurso; 403 sinaliza
falta de poder sobre uma capacidade que não é segredo.

## Testes

**Aceite** — `tests/Feature/Authorization/LeadVisibilityTest.php`: dois agentes no
mesmo tenant, um lead cada. Vendedor recebe 404 ao acessar o lead do colega;
gestor e admin recebem 200. Cobre leitura e escrita.

**Rede de segurança** — `tests/Feature/Authorization/RouteAuthorizationTest.php`:
teste tabelado sobre as 22 rotas com parâmetro. Para cada uma, um vendedor mira o
recurso de outro agente e deve receber 404. Uma rota nova sem policy faz o CI
falhar.

**Regressão** — a suíte atual (37 testes) precisa continuar verde. Vários testes
existentes autenticam usuários que passarão a precisar de papel; o ajuste é nas
factories, não nos asserts.

## Fora de escopo

- Gating visual na UI (esconder botões por papel). Fase seguinte. Nenhum arquivo
  de `resources/js` é tocado neste trabalho.
- Renomear o campo `usuario_id` para `projeto_id` no upload de anexo de projeto.
  O equívoco fica documentado e validado corretamente; corrigir o nome exige
  mudar frontend e backend juntos.
- Tela de gerenciar agentes e atribuir papéis. A permission `agentes.manage` fica
  definida, sem consumidor ainda.
- Partição de arquivos por tenant no disco público — dívida conhecida, registrada
  em conversa anterior, independente deste trabalho.
- Um usuário pertencer a mais de um tenant. A v1 da multi-tenancy fixou um tenant
  por usuário e nada aqui muda isso.

## Riscos

**Regressão silenciosa de visibilidade.** Trocar `where('user_id', auth()->id())`
por `visibleTo($user)` altera o que gestor e admin veem em quatro endpoints. Se o
mapeamento de permissões estiver errado, um vendedor pode ganhar visão ampla sem
que nenhum teste reclame. Mitigação: o teste de aceite fixa os três papéis
explicitamente.

**Cache de papéis entre requests.** É o erro clássico do modo teams. Mitigação: o
`unsetRelation` no `IdentifyTenant`, e um teste que alterna dois usuários de
tenants diferentes na mesma execução.

**Volume de mudança.** 22 rotas e 42 métodos num diff só. Mitigação: a
implementação é sequenciada em fases (fundação → policies → rotas → requests),
cada uma verde antes da seguinte.
