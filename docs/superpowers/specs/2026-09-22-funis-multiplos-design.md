# Funis múltiplos e customizáveis por tenant

Data: 2026-09-22
Branch: `master`

## Problema

Existia um conjunto único de "Tags" servindo de estágio de lead. Ele já era por
tenant (`tags.tenant_id`, semeado por `TenantBootstrapper`), mas era **fixo**:
seis linhas criadas no cadastro da empresa, sem nenhuma tela para alterá-las. A
única forma de customizar era escrever uma migration.

Faltavam duas coisas distintas, e vale separá-las porque só a segunda é o pedido:

1. **CRUD** — renomear, reordenar, criar e arquivar estágios.
2. **Múltiplos funis** — um funil de vendas e um de pós-venda convivendo, cada
   um com seus próprios estágios.

Três pontos do código pressupunham o conjunto fixo e quebrariam com funis
customizáveis:

| Ponto | O que assumia |
|---|---|
| `Dashboard.vue` | `payload.tag_id = 1` no cadastro de lead — id chutado no cliente |
| `Dashboard.vue` | `[3,4,5].includes(estagio.id)` como definição de "arquivado" |
| `Kanban.vue` / `Dashboard.vue` / `Perfil.vue` | paleta de cores indexada por id 1..6 |

E `configuracoes.manage`, citada no pedido, não existia em código: ela só
aparecia no spec de RBAC, com `spatie/laravel-permission` não instalado.

## Decisões

| Decisão | Escolha | Motivo |
|---|---|---|
| Relação lead ↔ funil | **Um funil por vez** (`usuarios.funil_id` + `estagio_id`) | Um lead em dois funis simultâneos exigiria pivot e mudaria toda consulta que hoje lê `usuarios.estagio_id`. O caso real — vendas depois pós-venda — é sequencial. |
| Semântica do estágio | **`tipo` enum**: `aberto`, `ganho`, `perdido` | Com nomes livres por tenant, nenhum código pode inferir significado de string. Alinha o estágio de lead com o que `status` de projeto já fazia via `is_won`/`is_lost`. |
| `is_active` | **Removida** | Manter as duas deixaria duas fontes de verdade discordando sobre o mesmo estágio. |
| Nomenclatura | **Rename completo**: `tags`→`estagios`, `tag_id`→`estagio_id` | "Tag" sempre significou estágio; o nome só se sustentava enquanto o conjunto era fixo. Com funis, ele colide de frente com "estágio de um funil". |
| Transição entre funis | **Só manual**, com estágio de destino explícito | Transição automática por estágio exigiria detectar ciclos e teria que ser configurada antes de existir uso real que a justifique. |
| RBAC | **Base mínima agora** | Instala o pacote e semeia papéis/permissões do spec de RBAC; aplica só `configuracoes.manage`. As 8 policies e o fechamento do IDOR continuam sendo tarefa daquele spec. |
| Faseamento | **Três fases**: rename → modelo → UI | O rename toca três arquivos de frontend de 700–1500 linhas. Isolá-lo faz a suíte distinguir "renomeei errado" de "a lógica de funil está errada". |

## A ambiguidade de `is_active` → `tipo`

É o único ponto do trabalho em que os dados não decidem sozinhos, e ele muda um
número visível no dashboard.

`is_active` é booleano; `tipo` tem três valores. O mapeamento de `true` é direto
(`aberto`). O de `false` não é: no conjunto semeado, `is_active = false` cobre
três naturezas diferentes.

| Estágio | `is_active` | `tipo` | Decidível? |
|---|---|---|---|
| Em captação, Em negociação, Em desenvolvimento | `true` | `aberto` | sim |
| Concluído | `false` | `ganho` | sim, pelo nome |
| Cancelado | `false` | `perdido` | sim, pelo nome |
| **Pausado** | `false` | **`aberto`** | **não** |

"Pausado" não foi ganho nem perdido — continua em aberto, apenas parado.
Classificá-lo como perdido inflaria a perda; como ganho, seria falso. Ele vai
para `aberto`, que é o que ele de fato é.

**Consequência visível:** para tenants com leads em "Pausado", `leads_ativos`
sobe e `leads_arquivados` desce em relação ao número de ontem. A mudança corrige
uma classificação que já estava errada — a antiga tratava "parado" e "encerrado"
como a mesma coisa.

O reconhecimento de ganho/perdido é **por nome e só no backfill**, uma tentativa
única sobre os nomes que `TenantBootstrapper` semeava. Estágio renomeado pelo
tenant não casa e cai em `aberto`, o default seguro: ele aparece no funil e pode
ser reclassificado na tela. Nenhum código depois da migração volta a inferir
significado a partir de nome.

## Arquitetura

### 1. Schema

```
funis           id, tenant_id, nome, descricao, ordem, is_default, softDeletes
estagios        + funil_id (FK restrict), + tipo enum, + cor         − is_active
usuarios        + funil_id (FK restrict)
estagio_historicos  + funil_anterior_id, + funil_novo_id
```

`usuarios.funil_id` é redundante com `estagios.funil_id` de propósito: permite ao
Kanban filtrar os leads de um funil sem join, e sustenta "um lead vive em um
funil" mesmo com estágio nulo. A consistência entre os dois é validada na
escrita, nunca assumida.

As colunas de funil em `estagio_historicos` não têm FK, pelo mesmo motivo das de
estágio: o histórico precisa sobreviver à remoção do que ele descreve. E elas
existem porque reconstruir "saiu de Vendas e entrou em Pós-venda" via join com
`estagios` daria a resposta errada depois que o estágio for renomeado ou movido.

### 2. Invariantes

Vivem em `App\Services\Funis\*`, não nos controllers, porque nenhuma delas é
uma escrita só — e porque o serviço é chamável de fora de um request.

| Invariante | Onde | Se violada |
|---|---|---|
| Todo funil tem ≥1 estágio `aberto` | `EstagioService` (arquivar e mudar tipo) | O cadastro de lead falharia longe da causa |
| O funil padrão não é arquivável | `FunilService` | Lead criado sem funil não teria onde cair |
| Funil com leads não é arquivável | `FunilService` | Leads num funil que nenhuma tela mostra |
| Estágio do lead é do funil do lead | `MoverLeadDeFunil`, `patchEstagio`, `UsuarioRequest` | O card some do quadro sem erro aparecer |
| Um `is_default` por tenant | `FunilService::definirPadrao` | Estado que nenhuma tela sabe representar |

Todas respondem 422 via `RegraDeFunilException`, que tem `render()` própria e não
exige handler em `bootstrap/app.php`.

Nota sobre estágio vs funil arquivado: um **estágio** arquivado continua
aparecendo no Kanban enquanto segurar lead, como somente-saída — comportamento
que `KanbanArchivedEstagioTest` fixa desde antes desta feature. Um **funil** com
leads simplesmente não é arquivável. A assimetria é deliberada: uma coluna a mais
no quadro é barata, um quadro inteiro fantasma não é.

### 3. Rename e o activity log

O activity log guarda o FQCN do subject, e o comando `activities:backfill-leads`
usa a tripla `(subject_type, subject_id, event)` como chave de idempotência.
Renomear `UsuarioTagHistorico` → `EstagioHistorico` sem migrar as linhas já
gravadas faria a chave deixar de casar, e a próxima execução do backfill
inseriria de novo **todo** evento histórico de troca de estágio.

A migração de rename faz esse `UPDATE`. Verificado contra cópia do banco de
desenvolvimento: 65 linhas antes, 65 depois de duas execuções do backfill, zero
duplicatas.

As linhas **já gravadas** mantêm as chaves antigas dentro de `properties`
(`tag_id_anterior`/`tag_id_novo`). Histórico é imutável e não é reescrito.

### 4. Nomes de índice determinísticos

As migrations criam cada índice de suporte de FK **explicitamente, com nome
próprio**, em vez de deixar o MySQL criá-lo.

Deixar implícito funciona no `up()` e torna o `down()` indefinido: quando uma
migration posterior adiciona um índice composto começando pela mesma coluna, o
MySQL passa a aceitá-lo como suporte da FK, e remover esse composto vira erro
1553 (`needed in a foreign key constraint`). Foi o que aconteceu com
`estagios_tenant_id_funil_id_index` no primeiro teste de rollback: a reversão
quebrou na sexta das oito migrations, deixando o banco pela metade.

Em cada `down()`, a ordem é FK primeiro, índice depois.

### 5. Base de RBAC

`spatie/laravel-permission` ^6.25 em modo teams, `team_foreign_key => tenant_id`,
conforme o spec de autorização. Papéis e permissões são globais (`roles.tenant_id
= null`), semeados uma vez; o que é por empresa é a atribuição.

`IdentifyTenant` chama `setPermissionsTeamId($tenant->id)` e
`unsetRelation('roles')` — o segundo é exigido pela documentação do pacote:
relações resolvidas antes da troca de team ficam em cache no model.

O primeiro usuário de cada tenant vira `admin` na migration de seed, e quem
registra uma empresa nova vira `admin` no `RegisteredUserController`. Sem isso a
feature subiria com a tela inacessível para todo mundo.

`HandleInertiaRequests` compartilha a permissão como **closure**, não valor
pronto: `Inertia::share()` roda antes de `$next($request)`, e portanto antes do
middleware `tenant` definir o team — avaliar `can()` ali devolveria `false` para
todos.

## Escopo

**Incluído:** funis e estágios por tenant com CRUD e reordenação; `tipo`
semântico; cor por estágio; um funil por lead; transição manual com estágio de
destino; tela `/configuracoes/funis`; base de RBAC com `configuracoes.manage`;
rename completo de "tag" para "estágio".

**Fora:** `status` de projeto (outro eixo, não estava no pedido); transição
automática entre funis; métricas filtradas por funil; as 8 policies e o
fechamento do IDOR interno, que continuam no spec de RBAC.
