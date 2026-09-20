# Activity log

Data: 2026-09-20
Branch: `feature/activity-log`

## Problema

`Userarios::timeline($id)` monta o histórico de um lead em runtime: sete
consultas, concatenação em array PHP, `usort` e resposta inteira de uma vez. Sem
paginação, sem índice que sirva à ordenação, e cada tipo de evento novo exige
mais um bloco no mesmo método — que já tem 70 linhas.

Há um segundo problema, mais silencioso: **a timeline reescreve o passado**. As
fontes usam `SoftDeletes` e o método consulta com `where()` simples, então apagar
uma anotação apaga também o registro de que ela existiu.

Levantamento das nove fontes de evento:

| Evento | Origem | Gatilho |
|---|---|---|
| `lead_criado` | `usuarios` | created |
| `anotacao` | `anotacaos` | created |
| `arquivo` | `arquivos` | created |
| `projeto` | `projetos` | created |
| `projeto_anotacao` | `projetoAnotacaos` | created |
| `projeto_anexo` | `projetoAnexos` | created |
| `status_alterado` | `usuario_tag_historicos` | **updated** (troca de `tag_id`) |
| `tarefa_criada` | `tarefas` | created |
| `tarefa_concluida` | `tarefas` | **updated** (`concluido` vira true) |

São sete tabelas mais a própria linha de `usuarios`. Dois dos nove eventos não
são de criação — detalhe que decide quais observers escutam o quê.

A lógica de `UsuarioTagHistorico::create` está **duplicada** em
`Userarios::update()` e `Userarios::patchTag()`, com o mesmo bloco de
comparação `$tagAnterior !== $usuario->tag_id` em ambos.

## A tensão central: soft delete

Um activity log é append-only: registrado o upload, o evento permanece mesmo que
o arquivo seja apagado depois. O `timeline()` atual é o contrário. Os dois não
podem concordar em número de eventos.

Isto não é hipotético. No banco de desenvolvimento:

| Tabela | Linhas | Apagadas |
|---|---:|---:|
| `arquivos` | 16 | 15 |
| `anotacaos` | 4 | 1 |
| `projetoAnotacaos` | 2 | 1 |

O endpoint antigo reporta **um** evento de arquivo. Um backfill fiel produz
**dezesseis**. Portanto o critério de aceite original — "contagem no endpoint
novo bate com o `timeline()` antigo" — é impossível de satisfazer junto com um
log honesto. A resolução está nas decisões abaixo.

## Decisões

| Decisão | Escolha | Motivo |
|---|---|---|
| Pacote | `spatie/laravel-activitylog` **^4.12** | Instala em PHP 8.2 (traz `laravel-package-tools`). |
| Backfill e apagados | Inclui **todas** as linhas e gera também o evento de remoção, a partir de `deleted_at` | História verdadeira. O endpoint antigo é que era lacunar; esconder 15 uploads não é fidelidade, é perda. |
| Validação da virada | Paridade **restrita ao subconjunto vivo** | Comparação automatizada e rigorosa, sem exigir que o log minta. Ver "Testes". |
| Autorização do endpoint novo | Filtra por dono desde já | O RBAC está especificado e aprovado, mas não implementado. O endpoint não nasce com o IDOR; quando as policies chegarem, o filtro vira `authorize()`. |
| Ligação evento → lead | Coluna `lead_id` dedicada e indexada | Os eventos vêm de sete tabelas; sem uma coluna própria, "atividades do lead X" viraria sete `OR` com joins. |

## Arquitetura

### 1. Tabela

Publicar a migration do pacote e, **antes de rodar**, acrescentar duas colunas:

- `tenant_id` — isolamento, no mesmo padrão das demais tabelas de domínio;
- `lead_id` — o lead a que o evento pertence, qualquer que seja a tabela de origem.

Índice composto `(tenant_id, lead_id, created_at)`, que é exatamente o acesso do
endpoint paginado: filtra por tenant e lead, ordena por data.

### 2. Model

`App\Models\Activity`, estendendo `Spatie\Activitylog\Models\Activity` e usando a
trait `BelongsToTenant`. Registrado em `config/activitylog.php` na chave
`activity_model`.

Consequência a não esquecer: o `TenantScope` é **fail-closed**. O comando de
backfill roda fora do ciclo HTTP e precisa chamar `CurrentTenant::set()` para
cada tenant que percorrer, ou toda query lança `RuntimeException`.

### 3. Observers

Sete observers em `app/Observers`, registrados em `AppServiceProvider::boot()`.

| Observer | Escuta | Registra |
|---|---|---|
| `UsuarioObserver` | created, updated | `lead_criado`; `status_alterado` quando `tag_id` muda |
| `AnotacaoObserver` | created, deleted | `anotacao`, `anotacao_removida` |
| `ArquivoObserver` | created, deleted | `arquivo`, `arquivo_removido` |
| `ProjetoObserver` | created | `projeto` |
| `ProjetoAnotacaoObserver` | created, deleted | `projeto_anotacao`, `projeto_anotacao_removida` |
| `ProjetoAnexoObserver` | created, deleted | `projeto_anexo`, `projeto_anexo_removido` |
| `TarefaObserver` | created, updated | `tarefa_criada`; `tarefa_concluida` quando `concluido` vira true |

Cada observer resolve o `lead_id` pela mesma cadeia de propriedade que o spec de
RBAC usa nas policies:

- direto: `Usuario` (o próprio id);
- um salto: `Anotacao`, `Arquivo`, `Tarefa`, `Projeto` (via `usuario_id`);
- dois saltos: `ProjetoAnotacao`, `ProjetoAnexo` (via `projeto.usuario_id`).

**Fim da duplicação de `UsuarioTagHistorico`.** O `UsuarioObserver` passa a ser o
único lugar que grava a tabela antiga, no mesmo `updated` em que registra o
`status_alterado`. Os dois blocos idênticos em `update()` e `patchTag()` saem.

Efeito colateral desejado: hoje só aqueles dois métodos gravam histórico de tag.
Com o observer, qualquer caminho que altere `tag_id` passa a registrar — o que é
o comportamento correto e hoje não acontece.

### 4. Comando de backfill

```
php artisan activities:backfill-leads [--tenant=ID]
```

Percorre os tenants; para cada um, `CurrentTenant::set($tenant)` e então cada
tabela de origem com `withTrashed()`.

Dois pontos que decidem se o comando presta:

**Retroagir `created_at`.** O pacote carimba `now()`. Sem forçar a data original
da linha de origem, todos os eventos históricos colapsam no instante da execução
e a ordenação da timeline — a razão de ser da feature — é destruída.

**Idempotência.** Chave lógica de um evento de backfill: a tripla
`(subject_type, subject_id, event)`. O comando carrega as triplas já existentes
e insere apenas as ausentes, então rodar duas vezes não duplica.

Para a tripla ser única, **o subject de cada evento é sempre a linha de origem**,
nunca o lead. Importa em particular para `status_alterado`: se o subject fosse o
`Usuario`, um lead com três trocas de tag geraria três eventos com a mesma
tripla, e o backfill gravaria só o primeiro. O subject é a linha de
`usuario_tag_historicos`, e as três trocas ficam distintas. Vale o mesmo para
qualquer evento que se repita na vida de um lead.

| Evento | subject |
|---|---|
| `lead_criado` | `Usuario` |
| `status_alterado` | `UsuarioTagHistorico` |
| `tarefa_criada`, `tarefa_concluida` | `Tarefa` |
| demais | a própria linha de origem |

O `lead_id` continua apontando para o lead em todos eles — é o que o endpoint
filtra. O subject serve à identidade do evento; o `lead_id`, ao agrupamento.

Linhas apagadas geram **dois** eventos: a criação, datada de `created_at`, e a
remoção, datada de `deleted_at`.

### 5. Endpoint

```
GET /api/leads/{usuario}/atividades?page=
```

Paginado, ordenado por `created_at` desc. Usa route-model-binding em `{usuario}`
— o `TenantScope` já garante 404 para lead de outro tenant.

Autorização nesta fase: o lead precisa pertencer ao agente autenticado
(`user_id === auth()->id()`), no mesmo padrão dos endpoints de listagem já
endurecidos. Quando o RBAC entrar, isso vira `$this->authorize('view', $usuario)`
e ganha `leads.view-all` de graça.

### 6. Transição

O `timeline()` e a tabela `usuario_tag_historicos` **continuam funcionando** em
todas as fases abaixo. Os observers escrevem em paralelo.

| Fase | Entrega | Porta de saída |
|---|---|---|
| 1 | Pacote, migration, model, observers | Suíte verde; escrita dupla funcionando |
| 2 | Comando de backfill | Teste de idempotência passa |
| 3 | Endpoint novo + teste de paridade | Paridade no subconjunto vivo passa |
| 4 | `TimelinePanel.vue` passa a consumir o endpoint novo; `timeline()` marcado como deprecado | Revisão visual |

A fase 4 só começa depois que a paridade da fase 3 estiver verde. A remoção
definitiva de `timeline()` e de `usuario_tag_historicos` fica para depois deste
trabalho, quando o endpoint novo tiver rodado em produção.

## Testes

**Idempotência** — `tests/Feature/ActivityLog/BackfillIdempotencyTest.php`:
rodar `activities:backfill-leads` duas vezes produz a mesma contagem de
atividades. Critério de aceite explícito do pedido.

**Paridade** — `tests/Feature/ActivityLog/TimelineParityTest.php`: para um lead
com eventos em todas as sete fontes, os eventos do endpoint novo **restritos a
linhas-fonte não apagadas** batem com o `timeline()` antigo, evento a evento
(tipo e data), não apenas em contagem. É a versão executável do critério de
aceite, ajustada pela decisão sobre soft delete.

**Divergência esperada** — o mesmo teste fixa que um registro apagado produz
dois eventos no log novo e nenhum no antigo. Documenta a diferença como
intencional, em vez de deixá-la como surpresa.

**Observers** — cada um dispara e grava `lead_id` e `tenant_id` corretos,
incluindo os de dois saltos.

**Regressão** — a suíte atual (37 testes) segue verde.

## Riscos

**`TenantScope` no console.** O erro mais provável de toda esta entrega: o
comando roda, lança `RuntimeException` em vez de escrever, ou pior, escreve com o
tenant errado ao trocar de tenant no laço. Mitigação: teste do comando com dois
tenants, verificando que nenhum evento cruza.

**Escrita dupla divergente.** Entre as fases 1 e 4, observers e `timeline()`
coexistem. Um bug no observer só aparece no endpoint novo, que ninguém está
olhando ainda. Mitigação: o teste de paridade da fase 3 é a porta de saída.

**Backfill em produção.** Com `arquivos` a 94% de linhas apagadas no dev, o
volume real de eventos gerados pode surpreender. Mitigação: `--tenant=` permite
rodar um tenant por vez e conferir antes de seguir.

**Observer não dispara em escrita por query builder.** `Usuario::where(...)->update(...)`
não dispara eventos de model. Não há esse caminho hoje nos controllers, mas é a
armadilha clássica de observers e vale um comentário no código.

## Fora de escopo

- Remover `timeline()` e `usuario_tag_historicos`. Só depois da fase 4 rodar em produção.
- Registrar atividade de login, logout ou alteração de configuração — o log
  nasce restrito a eventos de lead.
- Interface de auditoria para o admin. A permission `agentes.manage` do spec de
  RBAC seria o lugar natural, quando existir.
- Implementar o RBAC. Segue aprovado e pendente, e continua bloqueado na
  definição do mapeamento permission→papel.
