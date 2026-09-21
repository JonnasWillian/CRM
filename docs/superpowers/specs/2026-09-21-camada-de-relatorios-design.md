# Camada de relatórios

Data: 2026-09-21
Branch: `master`

## Problema

`Userarios::metricas()` calcula nove métricas em nove consultas dentro do
controller, sem cache e sem período configurável. O método já foi endurecido
para usar `auth()->id()` e as colunas semânticas, mas continua sendo lógica de
negócio num controller que hoje tem quinze métodos.

Duas limitações de produto vêm junto:

- **Sem período.** Duas métricas têm janela temporal, cada uma hardcoded de um
  jeito: `leads_30_dias` usa 30 dias móveis, `valor_fechado_mes` usa o mês
  calendário corrente. Não há como pedir outra janela.
- **Sem cache.** Toda visita ao dashboard refaz as nove consultas.

## A distinção que organiza o trabalho: estado e fluxo

Sete das nove métricas não têm dimensão temporal nenhuma. Elas são retrato do
momento, não atividade numa janela.

| Natureza | Métricas | Período se aplica? |
|---|---|---|
| **Estado** | `leads_ativos`, `leads_arquivados`, `valor_projetos_abertos`, `leads_por_tag`, `total_leads`, `total_com_projeto`, `taxa_conversao` | Não |
| **Fluxo** | `leads_30_dias`, `valor_fechado_mes` | Sim |

Filtrar uma métrica de estado por janela ou não significa nada, ou muda
silenciosamente o que ela mede — "leads ativos" viraria "leads criados na janela
que estão ativos", e o frontend exibiria outro número sob o mesmo rótulo. Por
isso **o período se aplica apenas às duas métricas de fluxo**.

## Decisões

| Decisão | Escolha | Motivo |
|---|---|---|
| Redis | **Adiado** | Não há servidor, extensão `phpredis` nem `predis` nesta máquina. Instalar exige pacote de sistema e mexe também em sessions e queues. |
| Cache agora | `Cache::remember` com chave composta, no store `database` atual | A chave especificada é chave simples, e chave simples funciona no `database`. Só *tags* exigem Redis: confirmado que apenas `ArrayStore`, `ApcStore`, `MemcachedStore`, `NullStore` e `RedisStore` estendem `TaggableStore`. |
| Alcance do período | Somente métricas de fluxo | Preserva o significado de cada número e mantém compatibilidade real com o frontend, não apenas de formato. |
| `valor_fechado_mes` | Mantém `updated_at`, com a limitação documentada | Corrigir exige data de fechamento no projeto ou log de mudança de status de projeto — escopo próprio. |
| Assinatura do período | Objeto `Periodo`, não `array` | Ver "Periodo" abaixo: um array não garante ordem de chaves, e o hash da chave de cache ficaria instável. |

## Arquitetura

### 1. `App\Services\Reports\Periodo`

Objeto de valor com construtores nomeados:

```php
Periodo::preset('7d'|'30d'|'mes_atual'|'mes_anterior');
Periodo::customizado(CarbonInterface $inicio, CarbonInterface $fim);
Periodo::padrao(); // 30d — o comportamento de hoje
```

Expõe `inicio()`, `fim()`, `rotulo()` e `hash()`.

O motivo de ser objeto e não `array`: `hash()` precisa ser canônico. Com um
array, `md5(json_encode($periodo))` muda conforme a ordem das chaves, e duas
requisições do mesmo período gerariam chaves de cache diferentes — o cache
fragmenta em silêncio e nunca acerta. O objeto normaliza para
`inicio|fim` antes de hashear.

`mes_anterior` é o mês calendário completo anterior, não os últimos 30 dias.

### 2. `App\Services\Reports\LeadMetricsService`

```php
public function resumo(Tenant $tenant, ?User $agente, Periodo $periodo): array
```

`$agente` nulo significa o tenant inteiro; preenchido, apenas a carteira daquele
agente. **É assim que a regra de visibilidade entra sem depender do RBAC**: quem
decide passar nulo é o chamador, não o serviço. Hoje, sem as policies, o
controller sempre passa o usuário autenticado, e o comportamento fica idêntico
ao atual. Quando `leads.view-all` existir, o controller passa nulo para gestor e
admin, e o serviço não muda uma linha.

**Guarda contra vazamento de métrica entre tenants.** O serviço verifica que
`$tenant->id` é igual ao tenant ativo do `CurrentTenant` e lança se não for. Sem
essa checagem, chamar `resumo($tenantA, ...)` com o `CurrentTenant` em B
devolveria dados de B gravados sob a chave de cache de A — exatamente o
vazamento que a chave composta existe para evitar, e que a chave sozinha não
impede. O `TenantScope` filtra a consulta pelo tenant ativo; ele não sabe nada
sobre o argumento passado ao serviço.

### 3. Cache

```
tenant:{tenantId}:metricas:{agenteId|todos}:{periodoHash}
```

TTL de 5 minutos, vindo de `config('reports.metricas_ttl')` com default 300, para
ser ajustável por ambiente sem alterar código. Agente nulo vira o literal
`todos`, para não colidir com a interpolação de `null` em string vazia.

Os três componentes são obrigatórios e cada um responde por um eixo de
isolamento: o tenant impede vazamento entre empresas, o agente impede que o
número do gestor apareça para o vendedor, e o período impede que uma janela
sirva outra.

**Invalidação.** Nesta fase, só TTL. Quando invalidação seletiva for necessária,
ela **não exige Redis**: basta um contador de versão por tenant embutido na
chave (`tenant:{id}:metricas:v{n}:...`), incrementado quando algo que afeta
métrica muda — os observers da fase de activity log são o gancho natural.
Incrementar a versão aposenta todas as chaves daquele tenant de uma vez.
Registrado aqui para que a migração para Redis seja decidida por outros motivos
— sessions, queues, filas — e não por esta necessidade.

### 4. `App\Http\Controllers\DashboardController`

Método `metricas(Request $request)`:

- valida `periodo` (um dos presets) ou `data_inicio`/`data_fim` para customizado;
- resolve o agente — hoje sempre `auth()->user()`;
- delega ao serviço e devolve JSON.

A rota `POST /api/metricas` passa a apontar para ele, e `Userarios::metricas()`
é removido. A URL não muda, então o frontend segue funcionando sem alteração.

### 5. Formato da resposta

As nove chaves atuais são mantidas com os mesmos nomes, inclusive
`leads_30_dias` e `valor_fechado_mes`, que passam a refletir o período pedido
mesmo quando ele não é 30 dias nem o mês corrente. Os nomes ficam imprecisos, e
é a troca consciente para não quebrar o frontend agora.

Acrescenta-se uma chave `periodo` com `inicio`, `fim` e `rotulo`. Acrescentar
chave é retrocompatível, e é o que permitirá ao frontend rotular corretamente
quando ganhar o seletor de período.

## Limitação conhecida: `valor_fechado_mes`

A métrica soma projetos com status ganho cujo `updated_at` cai na janela.
`updated_at` não é data de fechamento: qualquer edição num projeto já ganho o
move para a janela corrente, e o tira da janela em que foi realmente fechado.

Fica como está nesta fase, documentado no código. A correção pede uma coluna
`fechado_em` em `projetos` ou um log de mudança de status de projeto — o
`ProjetoObserver` criado na fase de activity log é o lugar natural, mas só
serviria do momento da mudança em diante, sem reconstruir o passado.

## Testes

**Paridade** — `LeadMetricsParityTest`: para um tenant com dados nas fontes
relevantes, `resumo()` com o período padrão devolve exatamente os mesmos nove
valores que o `Userarios::metricas()` de hoje. É a garantia de que a extração
não mudou número nenhum.

**Isolamento de cache** — `LeadMetricsCacheTest`: dois tenants com dados
diferentes, mesma janela; o segundo não recebe o resultado cacheado do primeiro.
Mesmo teste para dois agentes do mesmo tenant, e para duas janelas do mesmo
agente. É o critério que a chave composta existe para satisfazer.

**Guarda de tenant** — chamar `resumo()` com um tenant diferente do ativo lança,
em vez de devolver dados do tenant errado.

**Períodos** — cada preset produz a janela esperada, e `mes_anterior` devolve o
mês calendário completo, não 30 dias móveis.

**Regressão** — a suíte atual (53 testes) segue verde.

## Fora de escopo

- Migrar `CACHE_STORE`, `SESSION_DRIVER` e `QUEUE_CONNECTION` para Redis.
  Adiado por indisponibilidade de ambiente; vira tarefa própria.
- Invalidação seletiva de cache. TTL basta nesta fase; o caminho sem Redis está
  documentado acima.
- Seletor de período na UI. O backend passa a aceitar; o `Dashboard.vue`
  continua chamando sem parâmetro e recebendo o padrão de 30 dias.
- Corrigir a semântica de `valor_fechado_mes`.
- Novos relatórios além do resumo que já existe.

## Riscos

**Vazamento de métrica entre tenants.** O risco central de introduzir cache num
sistema multi-tenant, e a razão da chave composta. Mitigação em duas camadas: a
chave e a guarda de tenant no serviço, ambas cobertas por teste.

**Cache obsoleto.** Até 5 minutos de defasagem depois de cadastrar um lead. Para
um dashboard de CRM é aceitável; se incomodar, o contador de versão resolve sem
trocar de store.

**Renomeação implícita.** `leads_30_dias` deixa de significar 30 dias quando
outro período é pedido. Mitigação: a chave `periodo` na resposta carrega a
janela real, e o frontend passa a ter como rotular corretamente.
