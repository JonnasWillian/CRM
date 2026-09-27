# Modernização de UI/UX

Data: 2026-09-26
Branch: `master`

## Problema

Auditoria do sistema inteiro, com evidência:

| # | Achado | Evidência |
|---|---|---|
| 1 | Tokens de cor redeclarados em **14 arquivos** | 153 declarações duplicadas; já havia divergido em 4 tokens — dois verdes (`#34d399`/`#3ecf8e`), dois vermelhos (`#ef4444`/`#f06292`), dois `surface2`, quatro grafias de `--glow` |
| 2 | Kanban fora da navegação | `route('kanban')` aparecia 0 vez no layout; só alcançável por um toggle dentro do Dashboard |
| 3 | Lead sem URL própria | `/perfilUsuario` sem id, com o id em `sessionStorage` — link não compartilhável, nova aba errada, voltar quebrado |
| 4 | Acessibilidade | `focus-visible` em 1 arquivo; de ARIA só `aria-hidden`; **zero** `prefers-reduced-motion` |
| 5 | Movimento sem função | só hover; nada ligava o antes ao depois de uma mudança de estado |
| 6 | `--t3` reprovava em AA | `#4a5470` dava 2.96:1 na superfície elevada — e é a cor de rótulo, contador e data |

## Direção

**Instrumento de trabalho.** A sensação de "tecnológico" num CRM não nasce de
brilho: nasce do sistema parecer rápido e ciente — responder antes de você
terminar de pensar, mostrar para onde as coisas foram, deixar varrer o pipeline
sem ler palavra por palavra.

O que foi deliberadamente **não** feito, porque é o que produz "cara de IA":
glassmorphism, blobs de gradiente, glow, cartões flutuando em 3D, ícones em
emoji, selos de "powered by".

## Cor: escolhida por validação, não no olho

Cada cor passou pelo validador contra a superfície real:

```
texto       fg-0 15.4:1 · fg-1 7.0:1 · fg-2 4.8:1      (AA nas três superfícies)
categórica  deutan ΔE 9.3 · visão normal ΔE 17.4       (alvos 8 e 15)
```

**Verde e vermelho não são cores categóricas.** Sob deuteranopia eles colapsam
(ΔE 2.4 na primeira tentativa), e a banda de luminosidade não deixa espaço para
separá-los por claridade. Foram reclassificados como **status**, que por regra
aparecem sempre com rótulo em texto ao lado. A paleta categórica — as sugestões
de cor de estágio — exclui esse par.

`--fg-2` mudou de `#4a5470` para `#7683a6` por causa do achado 6.

## Movimento: quatro funções, e só quatro

| Onde | O quê | Por quê |
|---|---|---|
| Coluna de destino | borda e fundo acentuados enquanto o card paira | você vê para onde vai |
| Entrada de card | fade + 4px, escalonado 20ms, teto de 8 | o olho acompanha a chegada |
| Números do topo | contam até o novo valor em 400ms, **só na mudança** | diz "isto acabou de atualizar" |
| Foco de teclado | anel visível em todo controle | quem não usa mouse deixa de se perder |

Curva `cubic-bezier(.2,.8,.2,1)` — arranque rápido, pouso macio. Tudo colapsa
sob `prefers-reduced-motion`, zerando a duração em vez de remover a transição,
para que os estados finais continuem corretos.

## Arquitetura

### `resources/css/theme.css`

Fundação única, importada por `app.css`. Superfícies em **três** degraus
(`--bg-0/1/2`), e não dois — é o que permite hierarquia sem recorrer a sombra.

Os nomes antigos (`--bg`, `--surface`, `--t1`…) continuam existindo como
**aliases** apontando para os novos. Foi isso que permitiu apagar as 153
declarações locais sem reescrever regra por regra em 14 arquivos, e sem um
"big bang" impossível de revisar.

### Bug encontrado durante a fase 2: binding antes do tenant

`SubstituteBindings` roda no grupo de middleware; `tenant` é middleware de
rota, que roda depois. Como todo model de domínio usa `BelongsToTenant` e o
`TenantScope` é fail-closed, **toda rota com parâmetro de model devolvia 500**
em produção — incluindo `PUT /api/funis/{funil}`, `/api/estagios/{estagio}` e
`/api/motivos-perda/{motivo}`, entregues nas duas features anteriores.

Os testes não pegavam: `CurrentTenant` é singleton e o `setUp()` dos casos o
deixava preenchido antes da requisição, mascarando exatamente a condição de
produção.

Corrigido em `bootstrap/app.php` com `prependToPriorityList`, e travado por
dois testes que **limpam** o tenant de propósito antes da requisição.

### Fluxo

- `/leads/{id}` substitui `/perfilUsuario` + `sessionStorage`. O binding
  implícito resolve o model e o `TenantScope` devolve 404 entre empresas.
- Navegação declarada uma única vez e consumida pela barra e pela gaveta
  mobile — a duplicação anterior era o motivo de o Pipeline não existir em
  nenhuma das duas.

## Entregue

| Fase | Estado |
|---|---|
| 1. Fundação — tokens, tipo, espaço, motion, foco, reduced-motion | ✅ |
| 2. Shell e fluxo — sidebar, `/leads/{id}`, correção do binding | ✅ |
| 3. Pipeline — densidade, sinais, as quatro animações | ✅ |
| 4. Demais telas na mesma linguagem | ✅ |

Na fase 4, todas as telas sob o shell foram levadas aos tokens: **restaram 0
hexadecimais fixos** fora das telas de Auth, que seguem fora de escopo.

Dois achados que só apareceram **vendo a aplicação rodando**, e que nem o build
nem a suíte pegariam:

1. **As cores de estágio são dado, não CSS.** Semeadas por
   `TenantBootstrapper`, elas escaparam de toda a tokenização e continuavam na
   paleta antiga — a mesma que o validador reprovou por claridade. Corrigido no
   seed, nos swatches da tela de funis, e por migração para os tenants que já
   existiam. A migração casa pelo valor exato do default antigo: cor que o
   tenant escolheu não se mexe.
2. **As iniciais do card cortavam as duas primeiras letras do nome** — "DI"
   para Diego, "MA" para Marina. Viraram iniciais de nome e sobrenome.

## Fora de escopo

Paleta de comandos (`Cmd+K`); redesenho das telas de Auth, que ficam fora do
shell e não destoam de nada hoje.
