# Motivo de perda obrigatório

Data: 2026-09-26
Branch: `master`

## Problema

Quando um lead ou projeto é dado como perdido, o sistema registra *que* se
perdeu e não *por quê*. A pergunta "por que perdemos" não tem resposta possível
a partir do banco — nem para o mês passado, nem para ontem.

O trabalho não é adicionar um campo. É garantir que ele seja preenchido em todo
caminho que leva ao estado de perda, e guardá-lo de um jeito que sobreviva à
reabertura do negócio.

### Seis caminhos, não um

| Entidade | Caminho | Endpoint |
|---|---|---|
| Lead | Arrastar card no Kanban | `PATCH /api/usuarios/{id}/estagio` |
| Lead | Editar no Perfil | `PUT /api/usuarios/{id}` |
| Lead | Mover de funil | `PATCH /api/usuarios/{id}/funil` |
| Lead | Cadastrar já perdido (quick-add numa coluna perdida) | `POST /api/usuarios` |
| Projeto | Editar projeto | `PUT /api/projeto/{id}` |
| Projeto | Criar já perdido | `POST /api/projeto` |

Uma obrigatoriedade que só cobre o Kanban não é obrigatória — é uma sugestão com
cinco desvios, e o relatório nasce com buracos que ninguém consegue explicar
depois.

## Decisões

| Decisão | Escolha | Motivo |
|---|---|---|
| Formato | **Catálogo por tenant + observação livre opcional** | Texto livre não soma: "preço alto", "Preço muito alto" e "achou caro" são a mesma coisa para quem lê e três linhas para quem conta. A observação guarda o caso; o catálogo permite o agrupamento. |
| Armazenamento | **Tabela de eventos `perdas`** | Um negócio pode ser perdido, reaberto e perdido de novo. Com o motivo numa coluna da entidade, reabrir apagaria o dado ou o deixaria mentindo, e o relatório só saberia responder "por que os atualmente perdidos foram perdidos". |
| Catálogo | **Um só, compartilhado por lead e projeto** | Uma lista para manter. O relatório segmenta por tipo quando precisa, e a empresa que quiser separar nomeia os motivos com clareza. |
| Onde a regra mora | **Serviço único `AplicarTransicao`** | Declarar a regra em cada FormRequest a espalharia por quatro arquivos, e um sétimo caminho adicionado depois passaria sem ela sem nada quebrar. |
| Canal do erro | **`ValidationException`** | Quatro dos seis controllers envolvem a escrita num `catch (\Exception)` que vira 404. Uma exceção própria seria engolida e o usuário veria "Lead não encontrado" ao esquecer o motivo. |
| Escopo | **Base + leitura mínima** | A obrigatoriedade cobra atrito de todo vendedor desde o primeiro dia; sem nenhuma forma de ver o resultado, o atrito fica sem contrapartida. O relatório completo (período, cruzamentos, cache) continua sendo a camada de relatórios já especificada. |

## Arquitetura

### 1. Schema

```
motivos_perda   id, tenant_id, descricao, ordem, timestamps, softDeletes
perdas          id, tenant_id,
                perdivel_type / perdivel_id   (morph: Usuario | Projeto)
                motivo_perda_id -> motivos_perda  (restrict)
                observacao (500), valor, user_id, created_at
```

**`perdas` é append-only.** Sem `updated_at`: não existe "editar uma perda",
existe registrar outra. Reabrir um lead não apaga a perda dele.

**`valor` é fotografia, não referência.** O que se perdeu é quanto valia no
momento da perda — o preço do projeto naquele dia, a soma dos projetos abertos
daquele lead naquele dia. Calcular no relatório, a partir da entidade, daria o
valor de hoje, que mudou justamente porque o negócio foi perdido.

**FK restrict no motivo.** Remover fisicamente um motivo em uso reescreveria a
história do relatório. Arquivar (soft delete) é o caminho: o motivo some do
seletor e continua nomeando as perdas que já o citam.

### 2. O contrato `Perdivel`

Lead e projeto respondem à mesma pergunta — "isto está perdido?" — por caminhos
diferentes: o lead pelo `tipo` do estágio, o projeto pelo `is_lost` do status.
`App\Support\Perdas\Perdivel` esconde essa diferença do serviço:

```php
estadoAtualEhPerda(): bool
estadoSeriaPerda(array $atributos): bool   // atributos AINDA NÃO aplicados
valorDaPerda(): ?float
```

`estadoSeriaPerda()` recebe o payload não gravado. É o que permite **recusar
antes de salvar**, em vez de descobrir a perda depois — quando um observer
rodaria e já não haveria o que recusar.

### 3. `AplicarTransicao` — o ponto único

```php
$entraEmPerda = ! ($entidade->exists && $entidade->estadoAtualEhPerda())
                && $entidade->estadoSeriaPerda($atributos);
```

O `exists` importa: numa entidade ainda não salva, as relações já resolvem a
partir dos ids do payload, e `estadoAtualEhPerda()` responderia "já estava
perdido" para algo que está *nascendo* perdido — deixando passar exatamente o
caso que precisa de motivo.

Só a **entrada** em perda exige motivo. Editar o telefone de um lead já perdido
não pede nada, e não conta uma segunda perda.

`created_at` é setado pela aplicação, apesar do `DEFAULT CURRENT_TIMESTAMP` da
coluna: com `$timestamps = false` quem carimbaria seria o banco, e o relatório
inteiro é recortado por período.

### 4. Invariante do catálogo

**O tenant precisa de ao menos um motivo disponível.** Arquivar o último
tornaria impossível perder qualquer coisa, e a falha apareceria longe da causa —
no vendedor arrastando um card e recebendo um erro que ele não pode resolver.
Mesma família de "todo funil precisa de um estágio aberto".

Por isso também o catálogo é semeado para **todo** tenant na migração, inclusive
os vazios, e no `TenantBootstrapper` para os novos: não há como cadastrar um
motivo no meio do fluxo de arrastar um card.

### 5. Backfill

Leads e projetos já perdidos recebem `Não informado`. O relatório passa a
mostrar o próprio ponto cego — "100% das perdas: Não informado" é verdadeiro e
acionável (registre daqui pra frente), enquanto uma base que começa vazia
sugeriria falsamente que nunca se perdeu nada antes.

A data da perda vem de `estagio_historicos` quando o histórico a tem, e de
`updated_at` quando não — para projetos, que não têm histórico equivalente, é
sempre a aproximação. Verificado contra cópia do banco de desenvolvimento: dos
dois leads perdidos, um teve a data corrigida pelo histórico (19:27) em relação
ao `updated_at` (22:27).

`valor` fica nulo para leads no backfill: a soma dos projetos abertos no dia da
perda ninguém guardou, e um número inventado contamina o relatório de forma
silenciosa e permanente.

### 6. UI

Um componente só — `ModalMotivoPerda` — usado por Kanban, Perfil e
ProjetoPanel. Ele não escreve nada: devolve `{motivo_perda_id, observacao}` e
quem chamou decide, o que mantém o rollback com quem conhece o estado a desfazer.

No Kanban o drop é **otimista**: o card move, o modal pergunta, cancelar desfaz.
Bloquear o drop e exigir um menu trocaria o gesto natural do quadro por um
caminho escondido; o rollback é o mesmo que já existe quando a API falha.

### 7. Relatório

Barras horizontais ranqueadas, **uma cor para todas**. Os dados são a magnitude
de uma medida entre categorias, não identidades distintas — pintar cada motivo
de uma cor diferente sugeriria que são tipos distintos de coisa e faria a cor
seguir o rank, que muda a cada filtro. O comprimento já codifica a magnitude.

Cor `#6d5dfc` validada contra a superfície `#13192a`: banda de luminosidade,
piso de croma e contraste ≥ 3:1, todos PASS.

Gated por `leads.view-all` e não por `configuracoes.manage`: é a visão agregada
da carteira inteira, que é o que aquela permission separa.

## Escopo

**Incluído:** catálogo por tenant com CRUD, reordenação e arquivamento;
obrigatoriedade nos seis caminhos; tabela de eventos; modal compartilhado nas
três telas; painel agregado por motivo com período e tipo.

**Fora:** histórico de perdas por entidade na tela do lead; cruzamento por
agente ou funil; export; cache e o objeto `Periodo` da camada de relatórios,
ainda não implementada (`docs/superpowers/specs/2026-09-21-camada-de-relatorios-design.md`).
