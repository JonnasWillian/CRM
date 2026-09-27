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
  `return true`. O `authorize()` do request cobre a permissão de classe
  (ex.: `leads.manage`) **e também a autorização de instância**, sempre que a
  requisição identificar um recurso — pelo parâmetro de rota ou por um id no
  corpo. Ver *"Correção de 27/09: onde a autorização de instância cabe"*, no
  fim deste documento: a redação original desta linha delegava a instância às
  policies, e isso deixou oito portas abertas.

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

---

# Revisão de 2026-09-27

O desenho acima continua valendo. O inventário, não: entre 15/09 e hoje
entraram funis múltiplos, motivo de perda obrigatório e a modernização de UI.
Esta seção reconcilia o spec com o código antes de ele virar plano.

## Parte 1 já está implementada

Entregue junto com os funis, sem ter sido nomeada como tal:

| Item do spec | Onde |
|---|---|
| `spatie/laravel-permission ^6.25` | `composer.json` |
| `teams => true`, `team_foreign_key => tenant_id` | `config/permission.php` |
| `User` com `HasRoles` | `app/Models/User.php` |
| Papéis e permissions globais, semeados por migration | `2026_09_22_100008_seed_roles_and_permissions` |
| `setPermissionsTeamId` + `unsetRelation` | `app/Http/Middleware/IdentifyTenant.php` |

### Um pré-requisito que o spec não tinha

O mecanismo central deste desenho é route model binding. Ele **não funcionava**:
`SubstituteBindings` roda no grupo de middleware e `tenant` é middleware de
rota, que roda depois — então o binding resolvia models sem tenant ativo e o
`TenantScope`, que é fail-closed, derrubava a requisição com 500.

Corrigido em `bootstrap/app.php` via `prependToPriorityList`, e travado por
`tests/Feature/Navegacao/UrlDoLeadTest.php`, que limpa o `CurrentTenant` de
propósito antes da requisição — a condição real de produção, que o `setUp()`
dos outros testes mascarava.

Sem essa correção, implementar este spec produziria 500 em toda rota com
parâmetro.

## Mapeamento de permissões: confirmado

A tabela marcada como *"confirmar"* na versão original foi confirmada em
27/09, sem alteração. `leads.view-all` para admin e gestor;
`configuracoes.manage` para admin e gestor; `agentes.manage` só admin.

## O inventário mudou: 22 → 36 rotas com parâmetro

**Quatorze rotas nasceram depois do spec** — funis (6), estágios (3), motivos
de perda (3), atividades do lead (1), mover lead de funil (1).

**Três foram renomeadas:** `/api/tags` → `/api/estagios`,
`/api/usuarios/{id}/tag` → `/api/usuarios/{id}/estagio`, e `/perfilUsuario`
(sem identificador, com o id em `sessionStorage`) → `/leads/{usuario}`.

## Quatro models novos, nenhuma policy nova

`Funil`, `Estagio` e `MotivoPerda` são **configuração do tenant**, não dado de
carteira: quem pode mexer neles é quem tem `configuracoes.manage`, e as rotas
já carregam esse gate. Não existe "meu funil" e "funil do colega" — existe o
funil da empresa.

`Perda` não tem rota própria: ela é lida pelo relatório agregado, já barrado
por `leads.view-all`, e escrita pelo `AplicarTransicao` no contexto de um lead
ou projeto cuja autorização é a da entidade-mãe.

**As 8 policies do spec original continuam sendo as 8 corretas.** O trabalho
real são as ~21 rotas legadas com `{id}`, incluindo a armadilha já documentada
na versão original: `/projetoAnotacao/{id}` e `/projetoAnexo/{id}` significam
coisas diferentes conforme o verbo.

## Um bug existente que as policies corrigem

`LeadAtividadeController::index` carrega uma autorização provisória:

```php
abort_unless($usuario->user_id === auth()->id(), 404);
```

Ela está **errada para gestor e admin**: impede que vejam as atividades de um
lead da própria equipe. Substituí-la pela `UsuarioPolicy` não só fecha o IDOR —
conserta um comportamento quebrado que está em produção.

## Correção de 27/09: onde a autorização de instância cabe

A versão original deste spec dizia, na seção 7, que *"a autorização de instância
fica nas policies (o controller já terá o model resolvido)"*. **A premissa é
falsa para metade dos casos**, e a execução do plano encontrou oito portas
abertas por causa dela.

Ela vale para `PUT`/`DELETE` com route model binding: ali o parâmetro de rota
identifica o recurso, o binding resolve o model, e o controller autoriza o
objeto que já tem em mãos.

Ela não vale para **`POST` que referencia outro recurso por id no corpo**. Não
há parâmetro de rota, logo não há binding, logo não há model resolvido, logo a
policy nunca é consultada. Para a policy, `POST /api/anotacao` com o
`usuario_id` do lead de outro agente é indistinguível de uma anotação legítima.

Duas consequências práticas, as duas verificadas em runtime durante a execução:

1. **A autorização de instância pertence ao `FormRequest::authorize()`, não ao
   corpo do controller.** O `FormRequest` valida antes de o controller rodar;
   autorizar depois da validação cria um oráculo — payload malformado contra o
   lead de um colega devolve 422, payload válido devolve 404, e a diferença
   entre as duas respostas confirma que aquele id existe.
2. **Todo endpoint que decide algo a partir de um id vindo do corpo precisa
   resolver e autorizar esse recurso**, com o id convertido explicitamente
   (`$this->integer('usuario_id')`).

As oito portas: `POST /buscarArquivo`, `POST /arquivos`, `POST /projetoAnexo`,
`POST /projetos`, `POST /anotacao`, `POST /projetoAnotacao`, `POST /tarefas`
junto de `POST /tarefa-padroes/aplicar`, e `POST /projeto`.

**Por que escaparam do levantamento original:** o inventário deste spec procurou
rotas *com parâmetro*. Nenhuma das oito tem parâmetro. A pergunta que as
encontra não é "esta rota tem `{id}`?" — é **"este método decide alguma coisa a
partir de um id de recurso, venha ele de onde vier, e chega a perguntar se você
pode?"**. Quem estender este sistema deve usar a segunda pergunta.

A rede em `tests/Feature/Autorizacao/TodaRotaDeEscritaAutorizaTest.php` existe
para que a nona porta quebre o build em vez de chegar em produção.

## Escopo desta implementação

Somente o que falta: as 8 policies, a conversão das rotas legadas para binding,
`Usuario::scopeVisibleTo`, os três `FormRequest` que ainda declaram
`authorize(): bool { return true; }`, a separação de `ArquivoRequest` em dois, o
fechamento das oito portas de id-no-corpo, e a rede de segurança sobre **todas
as rotas de escrita** — não só as que têm parâmetro, como dizia a versão
original desta linha.
