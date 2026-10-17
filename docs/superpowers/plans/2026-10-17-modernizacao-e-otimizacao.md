# Plano de ação — modernização e otimização do CRM (UserFlow / CRMLeader)

## Contexto

O sistema é um CRM de prospecção multi-tenant (Laravel 11 + Inertia 2 + Vue 3 + Tailwind 3 + MySQL 8). A base está sólida em segurança e integridade (RBAC com 8 policies, tenant fail-closed, soft deletes, 295 testes), mas a apresentação e as consultas de listagem ficaram para trás:

- **Consultas**: só a grade de leads e a timeline são paginadas. Kanban, projetos, tarefas, anotações, arquivos e tarefas pendentes carregam tudo com `get()`. Há N+1 em funis e motivos de perda, ~9 agregações sem cache por carga do dashboard, `whereMonth`/`whereDate` não indexáveis, e a camada de relatórios (período + cache) foi especificada em 21/09 e nunca implementada.
- **Rotas**: 6 rotas de API sem consumidor, nomes inconsistentes (`/usuarios`, `/leads`, `/usuarioPerfil`, `projetoAnotacao` vs `tarefa-padroes`), mesma URL com id de entidades diferentes por verbo, nenhuma rota de API nomeada e ~60 chamadas axios com URL cravada.
- **Frontend**: nenhuma primitiva compartilhada. Cada tela redeclara botões (11 famílias), modais (6 reimplementações), toasts (3) e helpers (`formatBRL` ×4, iniciais ×4). Tema escuro roxo com fontes carregadas por `@import` em só 4 páginas. Nenhuma grade tem ordenação, seletor de itens por página, seleção em massa ou visibilidade de colunas. Ações de linha escondidas até hover, `confirm()` nativo, falhas silenciosas (`catch {}`) em 12 pontos, ações destrutivas sem confirmação, telas de auth em inglês.
- **Legado**: `Welcome.vue`, `Inicial.vue` com números fictícios, 7 componentes Breeze mortos, `Userarios.php` (500 linhas, nome errado), `arquivo.php`, `Statu`, tabelas `anotacaos`/`projetoAnotacaos`/`projetoAnexos`, `estagio_historicos` substituída pelo activity log, `remember_token` em leads, `verified` sem efeito, `activitylog:clean` nunca agendado.

Os prompts do usuário ("Polimento" e "UI/UX") pedem: remover o desnecessário, consolidar consultas, revisar rotas, propor funcionalidades, ícones nas ações de grade com tooltip, grades com até 100 por página paginadas no banco com indicador e ordenação, performance e custo de servidor, responsividade fina, feedback orientativo e confirmações, design system sóbrio e profissional com paleta azul institucional sobre fundo claro.

**Resultado esperado**: uma só linguagem visual (clara, azul institucional), grades padronizadas e paginadas no banco, feedback consistente, rotas REST nomeadas, consultas agregadas e cacheadas, legado removido, e as funcionalidades novas: Equipe e transferência de leads, Lixeira, Importar/Exportar CSV, campos de negócio com forecast, página de Tarefas e relatório Visão geral.

## Decisões tomadas com o usuário

| Tema | Decisão |
|---|---|
| Tema visual | Claro, azul institucional / petróleo, texto grafite. Tema escuro removido. |
| Contexto | CRM de vendas: vocabulário de leads, pipeline, propostas, tarefas. |
| Novas features | Equipe + transferência de leads; Lixeira na interface; Importar/Exportar CSV; Valor estimado, probabilidade e previsão de fechamento no lead. |
| Navegação | Dashboard (KPIs + tarefas) separado de Leads (grade). Modelos de tarefa vai para Configurações. |

## Decisões minhas (julgamentos; reverta se discordar)

- **Fontes**: Inter (UI) + Manrope (títulos), self-host via `@fontsource-variable` (sem bunny.net/Google).
- **Confirmações**: remover `sweetalert2`; `useConfirm()` sobre `AppModal` (`<dialog>` nativo, foco preso, Esc, retorno de foco). Ações reversíveis e frequentes (mover estágio aberto, concluir tarefa, transferir 1 lead) **não** têm diálogo: têm toast com **Desfazer** (7 s). Sair (logout) não confirma.
- **Concluir tarefa** deixa de pedir confirmação: o backend já aceita reabrir, então o texto atual "não pode ser desfeita" é falso. Entra ação "Reabrir".
- **Kanban**: `vuedraggable@4` (SortableJS) para toque e autoscroll; mover por teclado via menu do card e `Shift+←/→`; colunas com scroll-snap em mobile.
- **Página inicial** (`Inicial.vue`) removida; `/` redireciona para login ou dashboard. Painel de marketing com números fictícios sai do Login/Register.
- **Nome do produto**: mantido "UserFlow" na interface (é o que o logo exibe); `APP_NAME=UserFlow` para o título da aba não duplicar. Trocar para "CRMLeader" é uma linha, se preferir.
- **URLs**: `/kanban` e `/profile` mantidas (menu diz "Pipeline" e "Perfil"); renomear não traz ganho e mexe em testes.
- **Página Tarefas** (`/tarefas`) e **Relatórios › Visão geral** entram como propostas: sem a primeira o widget "ver todas" do dashboard não tem destino; sem a segunda o gestor não compara vendedores. Ambas usam a infraestrutura de listagem e métricas que o plano já cria.
- **Importar leads** restrito a `leads.view-all` (gestor/admin); acessível pelo botão em Leads e pelos estados vazios, não pela sidebar.
- **Listas de configuração** (funis, estágios, motivos, status): sem paginação (bounded), com N+1 corrigido.
- **Lixeira**: sem exclusão definitiva nesta rodada (FKs `restrict` e LGPD exigem anonimização; fica no backlog). Sem expurgo automático.
- **Lead como negócio**: "Ganhos no período" soma `COALESCE(valor_fechado, valor_estimado)` dos leads com `fechado_em` no período e estágio `ganho`. `projetos.preco` continua como informação secundária ("Σ projetos abertos"). `fechado_em` é coluna de evento mantida por observer (não `updated_at`).
- **Probabilidade**: nenhum valor assumido no forecast; leads com valor e sem probabilidade são contados ("faltam N"). Estágio ganha `probabilidade_padrao` opcional que **pré-preenche** o lead só quando o campo está vazio.
- **Cache de métricas**: TTL 300 s + invalidação por contador de versão nos observers de lead/projeto.
- **Morph map** (`Relation::enforceMorphMap`) antes de qualquer rename de classe, para `activity_log` e `perdas` não quebrarem.
- **Verificação de e-mail**: `verified` removido das rotas; `MustVerifyEmail` fica no backlog.
- **Exportação**: streaming sem teto (`lazyById`), sem fila/e-mail. **Transferência**: sem e-mail de notificação (backlog L10); só activity log.
- **Funil de conversão por passagem de estágio** (relatório): backlog, depende de modelar histórico sobre o activity log após remover `estagio_historicos`.

## Arquitetura-alvo

**Backend**
- `app/Support/Listagem/{Paginacao,Ordenacao}` + `app/Http/Requests/Listagem/ListagemRequest` (abstrata): `page`, `per_page` (teto 100), `sort` (whitelist via `in:`), `dir`, `busca`. Resposta padrão `{data, meta{current_page,last_page,per_page,total,from,to}, sort{por,dir}, filtros}` para JSON e para props Inertia.
- Controllers JSON em `App\Http\Controllers\Api\`, páginas em `App\Http\Controllers\Web\` (todas as closures de `web.php` viram controllers com props iniciais: estágios, funis, status, agentes).
- Rotas REST nomeadas `api.*`, pai na URL de `index`/`store`, rotas rasas para `update`/`destroy`, `Route::pattern` numérico para ids (Anexo A).
- `Userarios` dividido em `Api\LeadController`, `Api\AnotacaoController`, `Api\KanbanController`, `Api\DashboardController`.
- Relatórios: `Services/Reports/{Periodo,LeadMetricsService}`, `Support/Reports/MetricasCache`, `config/reports.php`.
- Kanban agrupado no banco: `Queries/Kanban/QuadroDoKanban` (2 consultas de agregados + N consultas `LIMIT` por coluna), `GET api/kanban/colunas/{estagio}?page=`.
- Colunas denormalizadas em `usuarios`: `ultimo_contato_em`, `fechado_em`, `valor_estimado`, `valor_fechado`, `probabilidade`, `previsao_fechamento`.
- Erros 422 sempre `{message, errors}` (fim da chave `erros`). `HandleInertiaRequests` compartilha permissões completas, papéis e `flash`.

**Frontend**
- `resources/css/theme.css` v2 (tokens claros, Anexo B) + `base.css`; `tailwind.config.js` mapeia tokens; páginas sem `<style scoped>` (exceto DataTable/Kanban/Layout).
- `resources/js/Components/ui/` (Anexo C) e `Components/leads/` (`LeadFormModal`, `ModalMotivoPerda`, `ConfirmarGanhoDialog`, `TransferirDialog`, `MoverEstagioMenu`).
- `Composables/useListagem.js` (grade sincronizada com a URL via `router.get` + `only`), `useListagemAxios.js` (painéis do perfil), `useToast`, `useConfirm`, `usePrefsLocais`, `useUnsavedChanges`, `usePerda` (uma instância do modal de motivo).
- `utils/formatadores.js`, `utils/erros.js`, `utils/posicionar.js`; interceptor axios em `bootstrap.js`.
- Navegação (sidebar 232px, colapsa para 56px em `md`, gaveta `<dialog>` abaixo de `md`):

| Grupo | Item | Ícone lucide | Rota | Quem vê |
|---|---|---|---|---|
| — | Dashboard | `LayoutDashboard` | `dashboard` | todos |
| — | Leads | `Users` | `leads.index` | todos |
| — | Pipeline | `SquareKanban` | `kanban` | todos |
| — | Tarefas | `CheckSquare` | `tarefas.index` | todos |
| Análise | Relatórios | `BarChart3` | `relatorios.index` | `leads.view-all` |
| Configurações | Funis · Motivos de perda · Modelos de tarefa · Equipe | `GitBranch` · `CircleX` · `LayoutTemplate` · `UserCog` | `configuracoes.*` | manage / todos / `agentes.manage` |
| Sistema | Lixeira | `Trash2` | `lixeira` | todos |
| Rodapé | avatar + nome → menu: Perfil, Sair | `UserCircle`, `LogOut` | `profile.edit`, `logout` | todos |

Breadcrumb só em nível 2+ (`Leads / Ana Souza`), respeitando a origem (`?de=pipeline`). Título da aba `"{H1} · UserFlow"`. "Novo lead" é botão primário em Leads e Pipeline (mesmo `LeadFormModal`), nunca no header global; atalhos `N` (novo lead) e `/` (busca) em Leads e Pipeline.

## Fases de execução

Cada fase termina com a aplicação funcionando, `php artisan test` verde, `npm run build` limpo e commit em `master` (sem worktree, conforme memória do projeto). Fases 0–2 são aditivas; a 3 é a única com quebra de contrato, feita tela a tela com backend e frontend no mesmo commit.

### Fase 0 — Fundação (sem quebra)

Backend
- Nomear todas as rotas de API atuais com `api.*` (aditivo); `Route::pattern` numérico em `bootstrap/app.php`.
- `config/leads.php` (por_pagina 25, max 100, dias_estagnado 14, kanban cards_por_coluna 30, importação, transferência) e `config/reports.php` (`metricas_ttl` 300).
- Remover `verified` das rotas, `GET /api/user`, `Pages/Welcome.vue`.
- `activity:limpar` (comando próprio que itera tenants com `CurrentTenant::set()`; o do pacote lança pelo `TenantScope`) agendado `daily()` em `routes/console.php`; remover `inspire`.
- Pint: `pint.json` preset laravel, `vendor/bin/pint --test` no CI, commit de estilo isolado.
- `HandleInertiaRequests`: `auth.permissions` completo (closure, pelo motivo documentado no arquivo), `auth.roles`, `flash{sucesso,erro,id}`.
- `withCount` em `FunilController@index` e `MotivoPerdaController@index`; `Support/Db/ReordenarPorIds` (um `UPDATE … CASE`) nos 3 serviços de reorder; `TarefaPadraoController@aplicar` em transação.

Frontend
- `theme.css` v2 (Anexo B) **reapontando os aliases legados** (`--t1`, `--surface`, `--accent`…) para os novos valores, para as páginas não migradas renderizarem claras. `base.css` (body, `.num`, focus ring, scrollbar, reduced-motion, nprogress). `app.css` importa fontsource + os dois.
- `tailwind.config.js`: screens sm/md/lg/xl, cores/fontes/tamanhos/raios/sombras mapeados aos tokens, `forms({ strategy: 'class' })`, sem Figtree. `app.blade.php` sem bunny.net.
- `app.js`: remover `.use(Maska.default)` (maska 3 não tem default), título `t ? \`${t} · ${appName}\` : appName`, sem `progress.color`. `APP_NAME=UserFlow`.
- `package.json`: + `@fontsource-variable/inter`, `@fontsource-variable/manrope`, `vuedraggable`; devDeps `vitest`, `@vue/test-utils`, `jsdom`, `eslint`, `eslint-plugin-vue`, `prettier`, `eslint-config-prettier`, `axe-core`; − `mask`. Scripts `lint`, `format`, `test`.
- Criar `utils/formatadores.js`, `utils/erros.js`, `utils/posicionar.js`, composables `useToast`, `useConfirm`, `usePrefsLocais`, `useUnsavedChanges`, e os componentes `ui/` do Anexo C exceto DataTable/FilterBar/AppPagination (Fase 2). `Layouts/AuthLayout.vue` (card centrado, claro). Interceptor axios.
- `Layouts/AuthenticatedLayout.vue`: nova estrutura (tabela acima) com ícones lucide, colapso em `md`, gaveta `<dialog>` com Esc e retorno de foco, `ToastHost`, `ConfirmHost`, `#camadas`, watcher de `flash`. (O `ModalMotivoPerda` único via `usePerda` entra no layout na Fase 3a, quando a primeira tela migrada passa a usá-lo; até lá as instâncias atuais por página continuam.) Itens de páginas ainda inexistentes entram na fase em que a página nasce.
- Remover os 4 `@import` de fontes dos `<style>` de Dashboard, Perfil, Login, Register.

Testes: `RotasNomeadasTest` (parte 1), N+1 em funis/motivos com `DB::listen`, `LimpezaDoActivityLogTest`; Vitest `tokens.contraste.test.js` (≥4.5:1 texto, ≥3:1 focus ring), `AppModal`, `useConfirm`, `formatadores`.

### Fase 1 — Schema e observers (sem quebra)

- Migrations `2026_10_17_1000{01..08}` (Anexo D).
- `Relation::enforceMorphMap([...])` em `AppServiceProvider` (mesmo deploy da migration 7).
- Models: casts em `Projeto`; `Statu::$fillable` sem `id`; `TarefaPadrao::user()`; `Usuario::responsavel()`, `User::leads()`, `scopeDoTenant`, `scopeAtivos`; remover `Notifiable` de Usuario/Projeto/Estagio.
- `app/Support/Leads/RegrasDeLead::cadastro()` extraída de `UsuarioRequest` (reuso no job de importação) com os campos de negócio (`valor_estimado numeric|min:0`, `probabilidade integer|between:0,100`, `previsao_fechamento date`, `valor_fechado numeric|min:0`).
- Observers: `AnotacaoObserver::created` atualiza `ultimo_contato_em` via query builder (sem disparar `UsuarioObserver`); `UsuarioObserver::saving` mantém `ultimo_contato_em`, `fechado_em` e o pré-preenchimento de `probabilidade` pelo `probabilidade_padrao` do estágio, com uma única consulta `whereIn(...)->get(['id','descricao','tipo','probabilidade_padrao'])`; `ProjetoObserver::saving` mantém `fechado_em`; ambos chamam `MetricasCache::invalidar`.
- `User.ativo`: `LoginRequest::authenticate()` passa `'ativo' => true`; `IdentifyTenant` aborta 403 "Conta desativada." para sessão aberta.
- `PATCH api/funis/{funil}/restaurar` (`withTrashed`) e campo `probabilidade_padrao` em `EstagioRequest`.

Testes: `CamposDeNegocioTest`, `UltimoContatoTest`, `FechadoEmTest`, `MorphMapTest`, parte "conta desativada" de `DesativacaoTest`, restaurar funil em `ConfiguracaoDeFunisTest`.

### Fase 2 — Contrato de listagem, métricas e tarefas (aditivo)

Backend
- `Support/Listagem/{Paginacao,Ordenacao}`, `Requests/Listagem/ListagemRequest`. `ListagemDeLeadsRequest` passa a estendê-la: filtros atuais + `funil_id`, `user_id` (só com `leads.view-all`), `estagnados`, `previsao_de/ate`, `situacao` (`ativos|ganhos|perdidos|sem_projeto|com_projeto_aberto`, substitui o atual `status`; `ListagemPaginadaTest` atualizado); `colunasOrdenaveis`: nome, created_at, estagio (join `estagios.ordem`), ultimo_contato, valor_estimado, previsao_fechamento, responsavel. `ListagemDeLeads::query()` (Builder reutilizado por exportação, Kanban e ações em massa por filtro) + `__invoke` que pagina; `withExists` no lugar dos `COUNT(*) > 0`; `with(['estagio','responsavel:id,name'])`.
- `Services/Reports/Periodo` (presets 7d/30d/mes_atual/mes_anterior/custom, `hash()`), `LeadMetricsService::resumo(Tenant, ?User $agente, Periodo, ?int $funilId)` com `Cache::remember` em chave `tenant:{id}:metricas:v{versao}:{agente|todos}:{funil}:{hash}`, `Support/Reports/MetricasCache` (`invalidar` = `increment`), `MetricasRequest` (`periodo`, `de`, `ate`, `user_id`, `funil_id`), `Api\DashboardController@metricas`. Resposta: `periodo`, `estado{leads_ativos, leads_fechados, leads_por_estagio[], sem_contato_14d}`, `fluxo{leads_criados, ganhos{total,valor}, perdidos{total,valor}, taxa_ganho, ticket_medio}`, `forecast{valor_estimado_aberto, valor_ponderado, leads_com_previsao, sem_probabilidade, valor_projetos_abertos, por_mes[]}`. Mantém as 9 chaves antigas até a Fase 3b. `RelatorioDePerdas::resumo()` recebe `Periodo` + `user_id`/`funil_id` e ganha cache.
- `GET /api/tarefas` (`ListagemDeTarefasRequest`: `escopo` hoje/atrasadas/proximos_7/pendentes/concluidas, `user_id`, busca; sort data_limite/created_at/titulo; comparações de data sargáveis) e `GET /api/tarefas/resumo` (3 `count` + 2 `limit(10)`), substitutos de `tarefasPendentes`.

Frontend
- `ui/DataTable.vue` (+ `DataTableColunas.vue`), `AppPagination`, `FilterBar`/`FilterChips`/`DateRange`/`PeriodoSelector`, `Composables/useListagem.js` e `useListagemAxios.js` (Anexo C).
- Vitest: `DataTable.test.js`, `useListagem.test.js`, `useListagemAxios.test.js`.

Testes: `ContratoDeListagemTest`, extensão de `ListagemPaginadaTest`, `PeriodoTest`, `LeadMetricsParityTest`, `LeadMetricsCacheTest`, `ForecastTest`, `ResumoDeTarefasTest`, `ListagemGlobalDeTarefasTest`.

### Fase 3 — Normalização de rotas e migração das telas (quebra controlada, por tela)

Cada subfase: backend + frontend no mesmo commit; rotas antigas da tela removidas; erros passam a `errors`; `catch {}` substituído por interceptor/toast; Swal e `confirm()` por `useConfirm`.

**3a — Perfil do lead** (`/leads/{id}`)
- Backend: rotas aninhadas `api.leads.{anotacoes,arquivos,projetos,tarefas}.index/store`, `api.leads.tarefas.aplicar-modelos`, rasas `api.{anotacoes,arquivos,projetos,tarefas,tarefa-padroes,projeto-anotacoes,projeto-anexos}.*`, `api.projetos.{anotacoes,anexos}.index/store`; todas paginadas (padrão 20, teto 100). Controllers `Api\{Anotacao,Arquivo,Projeto,ProjetoAnotacao,ProjetoAnexo,Tarefa,TarefaPadrao}Controller`. FormRequests autorizam por `route('usuario')`/`route('projeto')` (fim do `usuario_id` no corpo, inclusive o bug do anexo de projeto). `LeadAtividadeController` aceita `?tipo=` (filtro no servidor). `Web\LeadController@show` entrega prop `lead` (estágio, funil, responsável, `withCount` de anotações/arquivos/projetos/tarefas pendentes, Σ projetos), `funis`, `estagios`, `status`.
- Frontend `Usuario/Perfil.vue`: migalhas; hero com avatar, nome (H1), **badge de estágio clicável** (abre `MoverEstagioMenu` com funil + estágio e as regras de perda/ganho), responsável + "Transferir" (`leads.view-all`, Fase 4a), e-mail/telefone + botão **WhatsApp** (`wa.me/<E.164>`), chips valor/probabilidade/previsão (Fase 4c), ações: "Nova tarefa", menu ⋯ (Mover de funil, Transferir, Excluir). `AppTabs` sincronizada com `?aba=` na ordem **Atividades · Tarefas · Anotações · Projetos · Documentos · Dados** (Atividades é a padrão). Aba Dados: `FormField` em duas colunas (Contato | Negócio), **sem funil/estágio** (vivem no badge), `useUnsavedChanges`. Abas carregam sob demanda e cacheiam enquanto a página vive.
- `ProjetoPanel`: badge por semântica de status (aberto neutro, ganho `--ok`, perdido `--danger`), `AppMoneyInput` (corrige o parse de "1.500,00"), total no cabeçalho ("3 projetos · R$ 45.000 em aberto · R$ 12.000 ganhos"), sub-abas `AppTabs sm`. `TarefaPanel`: concluir sem diálogo (toast + Desfazer), "Reabrir" nas concluídas, "Aplicar modelo ▾" no formulário e diálogo "Aplicar modelos" com data calculada ao lado de cada um. `TimelinePanel`: `FilterChips` → `?tipo=`. Documentos e Anotações com `useListagemAxios`; erro de upload por arquivo; "Limpar" pendentes confirma só com ≥3 arquivos.

**3b — Leads, Dashboard e Tarefas**
- Backend: `Web\LeadController@index` (props `leads{data,meta,sort,filtros}`, `estagios`, `funis`, `agentes` se view-all), `leads.store` web para o modal, `Web\DashboardController@index` (props diferidas `metricas`, `tarefas`, `periodo`, `presets`, `agentes`), `Web\TarefaController@index` (props `tarefas`, `escopo`, `agentes`), `Api\LeadController` substitui `Userarios` (apagado), ações em massa `POST api/leads/{transferir,mover-estagio,lixeira}` aceitando `ids[]` (≤200) **ou** `todos=true` + filtros da listagem (processado em `chunkById`; `mover-estagio` para `perdido` recebe um motivo para todos via `AplicarTransicao`), remoção de `tarefasPendentes`, `usuarioPerfil`, `POST /usuarios`, `/api/timeline`.
- Frontend `Pages/Leads/Index.vue`: `PageHeader` "Leads" + contador + botões Importar (view-all) · Exportar CSV · Novo lead; `FilterBar`: busca, Funil, Estágio (multi, do funil), Situação, Responsável (view-all), Criado em (`DateRange`), toggle "Sem contato há +14 dias", "Limpar (n)"; barra de seleção: "N selecionados · Transferir · Mover de estágio · Exportar selecionados · Excluir" + "Selecionar todos os N resultados" (envia filtros). `DataTable id="leads"`:

| Coluna | Conteúdo | Ordenável | Oculta abaixo de |
|---|---|---|---|
| Lead | avatar · nome (link) · e-mail | nome | — |
| Telefone | formatado + ícone WhatsApp | — | sm |
| Estágio | `AppBadge` com cor; funil em cinza se houver >1 | estagio | — |
| Valor estimado | R$ · "60%" em cinza | valor_estimado | md |
| Previsão | data; vermelha se vencida e aberto | previsao_fechamento | lg |
| Responsável | nome (view-all) | responsavel | lg |
| Último contato | relativo; âmbar ≥14d | ultimo_contato | md |
| Criado em | data (padrão desc) | created_at | lg |
| Ações | Abrir · WhatsApp · Transferir (view-all) · Mover para a lixeira | — | — |

  Estados vazios: sem leads / com filtro / vendedor sem carteira (Anexo F). `LeadFormModal` (`useForm`): seções Contato e Negócio (funil padrão, primeiro estágio aberto, valor, probabilidade pré-preenchida, previsão, responsável para view-all), botões Cancelar · Salvar · Salvar e abrir; estágio perdido encadeia o modal de motivo; fechar com campos preenchidos pergunta "Descartar o cadastro?".
- `Dashboard.vue`: `PageHeader` com saudação, `PeriodoSelector` (7d · 30d · Mês atual · Mês anterior · Personalizado, na URL) e select de vendedor (view-all); 6 `KpiCard` (Anexo F.3); "Leads por estágio" (`StatBar`, select de funil) | "Previsão de fechamento" (tile grande + "N leads · R$ X sem ponderar · faltam N probabilidades"); widget Tarefas com duas listas (Atrasadas | Hoje, ≤10, checkbox concluir com Desfazer, link ao lead, "Ver todas (N)" → `/tarefas?escopo=atrasadas`); "Sem contato há +14 dias" (5 itens → `/leads?estagnados=1`). Removidos: toggle Lista/Pipeline, grade, modal, donut (substituído por Taxa de ganho). Estado vazio da empresa com CTAs "Novo lead" e "Importar planilha".
- `Pages/Tarefas/Index.vue`: segmented Atrasadas · Hoje · Próximos 7 dias · Pendentes · Concluídas (`?escopo=`), Responsável (view-all), busca; `DataTable`: ☐ · concluir · Tarefa (título + anotação) · Lead (link) · Vencimento (asc, vermelho/âmbar) · Responsável · Ações Editar · Excluir; em massa Concluir N · Excluir N.
- Navegação ganha "Leads" e "Tarefas".

**3c — Pipeline** (`/kanban`)
- Backend: `KanbanRequest`, `Queries/Kanban/QuadroDoKanban`, `Api\KanbanController@{index,coluna,settings}`, `Web\KanbanController@index` (props `funis`, `funilAtual`, `agentes`). Contrato no Anexo A.2.
- Frontend `Kanban.vue`: header com select de funil (sempre visível; desabilitado se único), busca (`?q=`), Responsável (`?user_id=`), toggle "Só parados +14d", números ("48 leads · R$ 210.000 em aberto · R$ 84.000 ponderado"), menu ⋯ (Coluna padrão para novos leads…, Configurar funis), "Novo lead". Colunas 272px com régua de cor, contador, Σ valor e Σ ponderado (não em ganho/perdido), "Carregar mais" por coluna, scroll-snap abaixo de `md`. Card: iniciais · nome · valor + chip "60%" · idade (âmbar ≥14d) · previsão (vermelha se vencida) · menu ⋯ sempre visível (Abrir, Mover para estágio… ▸, Mover para outro funil…, WhatsApp, Transferir, Excluir). `vuedraggable` com rollback + toast "Desfazer"; `Shift+←/→` move para a coluna vizinha; `M` abre "Mover para…". Soltar em `perdido` → modal de motivo; em `ganho` → `ConfirmarGanhoDialog`. Quick-add com erros inline (e-mail duplicado: "Já existe um lead com este e-mail — abrir"). Removidos: engrenagem flutuante, botão "Lista", ícone solto de mover funil.

**3d — Configurações e Relatório de perdas**
- Backend: mover `Funil`, `MotivoPerda`, `PerdaRelatorio`, `LeadAtividade` para `Api\`; `Web\ConfiguracoesController@{funis,motivosPerda,modelosTarefa}` e `Web\RelatorioController@perdas` com props; `/configuracoes/modelos-tarefa` substitui `/modelos-tarefa`.
- Frontend `Configuracoes/Funis.vue`: cards por funil, estágios com arraste (alça `GripVertical`) **e** setas (teclado), modais `FunilFormModal`/`EstagioFormModal` (cor categórica só para `tipo=aberto`; campo probabilidade padrão), "Tornar padrão" com confirmação leve, arquivar com diálogo próprio, seção colapsada "Arquivados (n)" com Restaurar, erros 422 junto ao item. `MotivosPerda.vue`: arraste + setas, badge Ativo/Arquivado, coluna "Usado em N perdas" (link ao relatório), `EmptyState`. `ModelosTarefa.vue`: `DataTable` sem meta (título, prazo "+N dias", anotação), modal, H1 e subtítulo novos. `Relatorios/Perdas.vue`: `FilterBar` com `PeriodoSelector` + tipo + vendedor + funil, 3 `KpiCard`, `StatBar` por motivo. Fim do `sweetalert2`: `npm uninstall`.

Testes: `RotasNomeadasTest` (parte 2: legadas → 404), `FormatoDe422Test`, `PaginasComPropsTest` (`assertInertia`), `QuadroAgrupadoTest` (totais em SQL = cálculo em PHP; ≤ 5 + nº de colunas consultas), `AcoesEmMassaTest` (ids e filtros, cap, perda com motivo único), `TarefasCrudTest`, `ListagemDeProjetosDoLeadTest`; atualizar `VerbosDeLeituraTest`, `TodasAsTelasAbremTest`, `FormRequestsTest`, `KanbanArchivedEstagioTest`.

### Fase 4 — Novas funcionalidades

**4a — Equipe e transferência de leads**
- Backend: `Convite` model + `Services/Equipe/{ConvidarAgente,AceitarConvite,AlterarPapel,DesativarAgente,ReativarAgente,RegrasDeAdmin}` (`RegrasDeAdmin` extraída de `ExclusaoDeConta`); `AgentePolicy` (mesmo tenant + `agentes.manage`, senão 404), `ConvitePolicy`, `UsuarioPolicy::transferir`; `Services/Leads/TransferirLeads` (`umLead`, `varios`, `carteira` em `chunkById`), evento `responsavel_alterado` no activity log e `AtividadeResource`; `Mail/ConviteDeEquipeMail` (`ShouldQueue`, link assinado 7 dias); rotas `api.equipe.*`, `api.leads.{responsavel,transferir}`, web `configuracoes.equipe` e `convites.show/store` (guest). Desativar: impedido se é o próprio, o último admin, ou possui leads (com opção "transferir a carteira para…" no mesmo diálogo); apaga sessões do usuário.
- Frontend `Configuracoes/Equipe.vue`: header "Equipe · 5 membros · 1 convite pendente" + "Convidar membro"; segmented Ativos · Inativos · Convites; `DataTable`: Membro · Papel (badge; select inline exceto na própria linha) · Leads (link `/leads?user_id=`) · Entrou em · Ações Transferir leads… · Desativar/Reativar; Convites: E-mail · Papel · Enviado em · Expira em · Reenviar · Cancelar. Diálogo Convidar com descrição de cada papel. `Equipe/AceitarConvite.vue` (AuthLayout: "Carla convidou você para a Acme como Vendedor" → nome, senha → "Criar acesso e entrar"; estados expirado/usado/e-mail já cadastrado). `TransferirDialog` (individual e em massa: "12 leads de 3 responsáveis → [select]"), no hero do perfil, na grade e no card. Quem perde o lead vê 404 tratado como "Este lead não está mais na sua carteira" + "Ir para Leads".

**4b — Lixeira**
- Backend: `Api\LixeiraController@index` (`tipo=leads|projetos`, busca, sort `deleted_at|nome`; `excluido_por` via activity log numa consulta por página; projeto de lead excluído marcado `lead_na_lixeira`), `Web\LixeiraController@index`; `leads.show` aceita lead na lixeira (`withTrashed` + policy `restore`) em modo leitura.
- Frontend `Lixeira/Index.vue`: `AppTabs` Leads | Projetos, busca, Responsável (view-all), `DataTable` (Lead/Projeto · Estágio ou Lead · Responsável · Excluído em · Excluído por · Restaurar), restaurar em massa. Perfil de lead na lixeira: faixa "Este lead está na Lixeira" + Restaurar, escrita desabilitada. "Mover para a lixeira" na grade, no perfil, no card e no projeto, com confirmação e toast "Desfazer". Navegação ganha "Lixeira".

**4c — Campos de negócio, ganho e forecast no front**
- `LeadFormModal` e aba Dados: `AppMoneyInput` valor estimado, probabilidade (%) com sugestão do estágio, `AppDateInput` previsão. Card e grade exibem os três. `ConfirmarGanhoDialog`: valor fechado pré-preenchido com o estimado, data (hoje), aviso "Este lead tem N projetos em aberto; feche-os na aba Projetos se for o caso", botão "Marcar como ganho" (envia `estagio_id` + `valor_fechado` para `api.leads.estagio`; `fechado_em` vem do observer; probabilidade 100). Mover para perdido mostra o mesmo aviso de projetos abertos. Dashboard: tiles Ganhos/Perdidos/Taxa/Previsão alimentados por `fluxo` e `forecast`.

**4d — Importar / Exportar CSV**
- Backend: `Api\LeadController@exportar` (streaming, BOM UTF-8, `;`, `lazyById(500)`, respeita filtros, seleção e `visibleTo`; atividade `leads_exportados`); `Importacao` model, `Services/Importacao/PreviaDeImportacao` (delimitador/codificação, amostra, sugestão de mapeamento, teto de linhas), `Jobs/ImportarLeads` (`ShouldQueue`, tenant e causer definidos, validação por linha com `RegrasDeLead`, duplicados por e-mail `ignorar|atualizar` (atualizar não apaga campos vazios), e-mail repetido no próprio arquivo é erro, telefone/estágio/responsável inválidos viram aviso + padrão, lotes de 100, erros até 200 persistidos, evento `lead_importado`), rotas `api.importacoes.*`, comando `importacoes:limpar`; `can:leads.view-all`; README com `queue:work` e `MAIL_MAILER`.
- Frontend `Leads/Importar.vue` (wizard com URL por passo): 1 Arquivo (drop CSV ≤10 MB, "Baixar modelo CSV", separador auto, lista de colunas reconhecidas, "Importações anteriores") → 2 Mapeamento (coluna → campo com sugestão, prévia de 5 linhas, padrões: funil, estágio aberto, responsável; duplicados: Pular | Atualizar; validação "Nome é obrigatório") → 3 Processando (barra "312 de 1.240", polling 2 s, "pode sair desta página") → 4 Resultado (tiles Criados · Atualizados · Pulados · Com erro, tabela de erros, "Baixar linhas com erro (CSV)", "Ver leads importados"). Botão "Exportar CSV" na grade (diálogo curto: todas as colunas / visíveis, separador).

**4e — Relatórios › Visão geral**
- Backend: `Web\RelatorioController@index` (`/relatorios`), `Api\RelatorioController@visaoGeral` (tiles de `LeadMetricsService`, tabela por vendedor em uma consulta agrupada, ganhos por mês por `fechado_em`), `Api\PerdaRelatorioController@detalhe` (lista paginada de `perdas` com lead/projeto, motivo, valor, vendedor, data, observação).
- Frontend `Relatorios/Index.vue` (abas Visão geral | Por que perdemos, filtros comuns período/vendedor/funil): tiles Leads criados · Ganhos (nº, R$) · Perdidos (nº, R$) · Taxa de ganho · Ticket médio · Forecast; "Por vendedor" (`DataTable` ordenável, exportável); "Ganhos por mês" (`StatBar` vertical de 12 barras). `Perdas.vue` ganha a tabela de detalhe paginada e exportável. Navegação: "Relatórios".

Testes: `TransferenciaDeLeadsTest`, `ConviteTest`, `PapelTest`, `DesativacaoTest`, `ListagemDeEquipeTest`, `ListagemDaLixeiraTest` (+ extensão das lixeiras existentes, lead na lixeira em modo leitura), `GanhoDeLeadTest`, `ExportacaoCsvTest`, `PreviaDeImportacaoTest`, `ImportarLeadsJobTest` (dois tenants), `ImportacaoPolicyTest`, `VisaoGeralTest`, `DetalheDePerdasTest`.

### Fase 5 — Auth, página inicial e legado

- Auth em `AuthLayout`: Login (sem painel de marketing), Register (**+ campo "Nome da empresa"**, obrigatório, vira `Tenant.nome`; hoje recebe o nome da pessoa), Forgot/Reset/Confirm/Verify reescritos em português; `status` em `AppAlert`; apagar `GuestLayout`, componentes Breeze e `Inicial.vue`; `/` redireciona.
- Perfil do usuário: cards com `FormField`; card "Minha empresa" (nome editável por admin, papel em badge); preferência "Coluna padrão do Pipeline" também aqui; texto de exclusão aponta para Equipe.
- Legado (após as fases anteriores em produção e `activities:backfill-leads` executado): migrations 9–11 (Anexo D); remover `EstagioHistorico`, `TimelineParityTest` (substituído por `AtividadeResourceTest`); renomear `arquivo`→`Arquivo`/`Api\ArquivoController`, `Statu`→`StatusProjeto`; remover `Telefone::formatar`, `Perda::registradoPor`, `Activity::lead`, relações mortas; renomear chaves antigas das métricas no front.
- Frontend: apagar o bloco de aliases do `theme.css`; gates (todos zerados): `grep -rnE "#[0-9a-fA-F]{6}\b" resources/js --include=*.vue | grep -v 'fill="none"'`, `grep -rn "@media (max-width" resources/js`, `grep -rn "catch {" resources/js`, `grep -rn 'title="' resources/js --include=*.vue | grep "<button"`, `grep -rn "<style" resources/js/Pages`.
- Ao aprovar este plano, copiá-lo para `docs/superpowers/plans/2026-10-17-modernizacao-e-otimizacao.md` como registro.

## Verificação (fim a fim)

1. `php artisan test` (banco `CRMLeader_test`, nunca o de dev) e `npm run test` verdes a cada fase; `vendor/bin/pint --test` e `npm run lint` no CI.
2. Visual por fase com a skill `run` (`composer dev`) e Playwright MCP: login e, para cada página (dashboard, leads, leads/{id} ×6 abas, kanban, tarefas, funis, motivos, modelos, equipe, perdas, visão geral, lixeira, importar, profile, auth, convite), capturas em 375 / 768 / 1280. Checagens via `browser_evaluate`: sem scroll horizontal da página a 375 (exceto o container da grade), nenhum alvo interativo < 36 px, foco visível após Tab, Esc fecha modal e devolve foco, `axe.run()` sem violações `color-contrast` e `button-name`.
3. Consultas: `QuadroAgrupadoTest`, N+1 em funis/motivos e `MetricasSemListaDeIdsTest` com `DB::listen`; conferir com `php artisan pail` que uma carga do Dashboard com cache quente faz 1 consulta de métricas e que uma troca de página na grade faz 2 (dados + count).
4. Fluxos manuais: cadastrar lead rápido e completo; arrastar para perdido (motivo) e ganho (diálogo); mover de funil; transferir 1 e N (por ids e por filtro); convidar, aceitar convite, desativar com transferência; importar CSV com erro em linha, baixar o relatório, reimportar sem duplicar; exportar com filtros como vendedor; excluir e restaurar via Lixeira (lead e projeto de lead na lixeira); concluir e reabrir tarefa; aplicar modelos; editar e tentar sair sem salvar; desligar a rede e confirmar que todo bloco mostra erro com "Tentar de novo".
5. Antes da Fase 5 em produção: backup (`mysqldump`), `activities:backfill-leads`, migrations 9–11 testadas contra cópia da base.

## Anexo A — Contrato de rotas

### A.1 API (`auth`, `tenant`, `throttle:api`; todas nomeadas)

| Verbo | URI | Nome | Autorização |
|---|---|---|---|
| GET | /api/funis · /api/estagios · /api/status · /api/motivos-perda | api.funis.index · api.estagios.index · api.status.index · api.motivos-perda.index | tenant |
| POST/PUT/DELETE/PATCH | /api/funis…, /api/estagios…, /api/motivos-perda… (URIs atuais) + PATCH /api/funis/{funil}/restaurar | api.funis.*, api.estagios.*, api.motivos-perda.* | `can:configuracoes.manage` |
| GET/POST | /api/leads | api.leads.index/store | visibleTo / UsuarioRequest |
| GET | /api/leads/exportar | api.leads.exportar | visibleTo |
| POST | /api/leads/{transferir,mover-estagio,lixeira} | api.leads.transferir / mover-estagio / lixeira | view-all / policies por lead |
| GET/PUT/DELETE | /api/leads/{usuario} | api.leads.show/update/destroy | policy |
| PATCH | /api/leads/{usuario}/{restaurar,estagio,funil,responsavel} | api.leads.* | policy (`estagio` aceita `valor_fechado` opcional quando o destino é `ganho`) |
| GET | /api/leads/{usuario}/atividades?tipo= | api.leads.atividades.index | policy view |
| GET/POST | /api/leads/{usuario}/{anotacoes,arquivos,projetos,tarefas} | api.leads.*.index/store | policy view/update |
| POST | /api/leads/{usuario}/tarefas/aplicar-modelos | api.leads.tarefas.aplicar-modelos | policy update |
| PUT/DELETE | /api/anotacoes/{anotacao} · /api/tarefas/{tarefa} | api.anotacoes.* · api.tarefas.* | policies |
| DELETE | /api/arquivos/{arquivo} | api.arquivos.destroy | ArquivoPolicy |
| GET/PUT/DELETE · PATCH | /api/projetos/{projeto} · /restaurar | api.projetos.* | ProjetoPolicy |
| GET/POST | /api/projetos/{projeto}/{anotacoes,anexos} | api.projetos.*.index/store | policy |
| PUT/DELETE · DELETE | /api/projeto-anotacoes/{id} · /api/projeto-anexos/{id} | api.projeto-anotacoes.* · api.projeto-anexos.destroy | policies |
| GET | /api/tarefas · /api/tarefas/resumo | api.tarefas.index · api.tarefas.resumo | visibleTo |
| POST | /api/tarefas/{concluir,excluir} (massa) | api.tarefas.concluir / excluir | policies |
| GET/POST/PUT/DELETE | /api/tarefa-padroes[/{id}] | api.tarefa-padroes.* | próprio usuário / policy |
| GET | /api/kanban · /api/kanban/colunas/{estagio} | api.kanban.index · api.kanban.coluna | visibleTo |
| PATCH | /api/kanban/settings | api.kanban.settings | próprio usuário |
| GET | /api/metricas | api.metricas | visibleTo/agente |
| GET | /api/relatorios/{perdas,perdas/detalhe,visao-geral} | api.relatorios.* | `can:leads.view-all` |
| GET | /api/lixeira | api.lixeira.index | visibleTo (onlyTrashed) |
| GET | /api/equipe · /api/equipe/opcoes | api.equipe.index · api.equipe.opcoes | agentes.manage / leads.view-all |
| GET/POST · DELETE · POST | /api/equipe/convites[/{convite}[/reenviar]] | api.equipe.convites.* | agentes.manage + policy |
| PATCH | /api/equipe/{agente}/{papel,desativar,reativar} | api.equipe.* | AgentePolicy |
| POST | /api/equipe/{agente}/transferir-carteira | api.equipe.transferir-carteira | AgentePolicy |
| GET/POST · GET | /api/importacoes[/{importacao}] | api.importacoes.index/store/show | leads.view-all / policy |
| POST · GET | /api/importacoes/{importacao}/{iniciar,erros} | api.importacoes.iniciar / erros | policy |

Removidas: `GET /api/user`, `/api/timeline/{usuario}`, `/api/usuarioPerfil/{usuario}`, `/api/usuarios…`, `/api/anotacao/*`, `GET/POST /api/arquivos`, `/api/projetos?usuario_id`, `/api/projeto/*`, `/api/projetoAnotacao/*`, `/api/projetoAnexo/*`, `GET /api/tarefas/{usuario}`, `POST /api/tarefas`, `/api/tarefasPendentes`, `POST /api/tarefa-padroes/aplicar`.

### A.2 Kanban — resposta de `GET /api/kanban`

```json
{
  "funil": {"id":1,"nome":"Vendas","is_default":true},
  "funis": [...],
  "filtros": {"busca":null,"user_id":null,"estagnados":false,"por_coluna":30},
  "estagios": [{
    "id":3,"descricao":"Em negociação","ordem":2,"tipo":"aberto","cor":"#2b6cb0","arquivada":false,"probabilidade_padrao":40,
    "total":87,"valor_estimado":125000.0,"valor_ponderado":61250.0,"sem_probabilidade":4,"valor_projetos":42000.0,
    "leads":[{"id":1,"nome":"…","email":"…","telefone":"…","estagio_id":3,"responsavel":{"id":2,"name":"…"},
              "valor_estimado":5000.0,"probabilidade":60,"previsao_fechamento":"2026-11-30",
              "ultimo_contato":"2026-10-10T14:00:00Z","estagnado":false,"valor_projetos":0}],
    "proxima_pagina": 2
  }]
}
```
`GET /api/kanban/colunas/{estagio}?page=2` devolve `{data, meta, sort}` só daquela coluna.

### A.3 Web (Inertia)

| URI | Nome | Controller | Props |
|---|---|---|---|
| / | — | redirect → login ou dashboard | — |
| /dashboard | dashboard | Web\DashboardController@index | metricas (defer), tarefas (defer), periodo, presets, agentes |
| /leads | leads.index | Web\LeadController@index | leads{data,meta,sort,filtros}, estagios, funis, agentes |
| /leads/importar[/{importacao}[/mapeamento]] | leads.importar[.*] | Web\ImportacaoController | importacoes, importacao, funis, estagios, agentes |
| /leads/{usuario} | leads.show | Web\LeadController@show (`can:view,usuario`, withTrashed) | lead (+contadores), funis, estagios, status |
| /kanban | kanban | Web\KanbanController@index | funis, funilAtual, agentes |
| /tarefas | tarefas.index | Web\TarefaController@index | tarefas, escopo, agentes |
| /relatorios · /relatorios/perdas | relatorios.index · relatorios.perdas (`can:leads.view-all`) | Web\RelatorioController | relatorio, periodo, agentes, funis |
| /lixeira | lixeira | Web\LixeiraController@index | lixeira, agentes |
| /configuracoes/{funis,motivos-perda,modelos-tarefa} | configuracoes.* | Web\ConfiguracoesController | funis / motivos / modelos |
| /configuracoes/equipe | configuracoes.equipe (`can:agentes.manage`) | Web\EquipeController@index | agentes, convites, papeis |
| /convites/{token} | convites.show / convites.store (guest) | Web\ConviteController | convite{email,empresa,papel} |
| /profile | profile.edit/update/destroy | ProfileController | + tenant, papel |

## Anexo B — Tokens (`theme.css` v2)

```css
:root {
  color-scheme: light;
  --bg-0: #f4f6f9; --bg-1: #ffffff; --bg-2: #f0f3f7;
  --line: #dfe4ec; --line-2: #c8d0dc;
  --fg-0: #1b2230;  /* 15.9:1 */  --fg-1: #4b5565; /* 7.5:1 */  --fg-2: #5f6b80; /* 5.4:1 branco, 5.0:1 bg-0 */
  --fg-disabled: #98a2b3;
  --primary: #1b4f72; --primary-hover: #164060; --primary-active: #11324c; --primary-subtle: #e8eff7; --primary-fg: #fff;
  --focus-ring: #2f6fb0;
  --ok: #1a7544; --ok-subtle: #e6f4ec;
  --warn: #9a5b00; --warn-subtle: #fdf3e1; --warn-accent: #c47a12; /* só barras/ícones */
  --danger: #b42318; --danger-hover: #9b1c13; --danger-subtle: #fdeceb;
  --info: #1f5f99; --info-subtle: #e8f1fb;
  --cat-1: #2b6cb0; --cat-2: #9c6612; --cat-3: #6b46c1; --cat-4: #b83280; --cat-neutral: #4a5568;
  --font-body: 'Inter Variable', Inter, system-ui, sans-serif;
  --font-display: 'Manrope Variable', Manrope, var(--font-body);
  --fs-xs: 11px; --fs-sm: 12px; --fs-md: 13px; --fs-lg: 15px; --fs-xl: 18px; --fs-2xl: 22px; --fs-3xl: 28px;
  --lh-tight: 1.2; --lh-base: 1.45;
  --s-1: 4px … --s-10: 40px;  /* mantidos */
  --r-1: 4px; --r-2: 6px; --r-3: 10px; --r-full: 999px;
  --sh-1: 0 1px 2px rgb(16 24 40 / .06); --sh-2: 0 4px 12px rgb(16 24 40 / .08); --sh-3: 0 12px 32px rgb(16 24 40 / .14);
  --e: cubic-bezier(.2,.8,.2,1); --d-1: 120ms; --d-2: 180ms; --d-3: 260ms;  /* --e-spring removido */
  --page-x: 12px → 16px (md) → 24px (lg) → 32px (xl);
}
```
Hierarquia: H1 Manrope 22/600, H2 18/600, H3 Inter 15/600, corpo 13/400, rótulos 12/500 `--fg-2`. Verde e vermelho são cores de **status** (sempre com rótulo), nunca categóricas; estágio ganho usa `--ok`, perdido `--danger`. Cor de estágio vinda do banco nunca é usada crua como texto: `AppBadge` aplica `color-mix(cor 65%, fg-0)` no texto e `color-mix(cor 14%, bg-1)` no fundo. Migration de dados remapeia a paleta antiga de `Funis.vue` (`#499fca→#2b6cb0`, `#bd8939→#9c6612`, `#9268f3→#6b46c1`, `#e5618d→#b83280`, `#30ae77→#1a7544`, `#f15873→#b42318`). Aliases legados reapontados na Fase 0 e apagados na Fase 5. Sem `backdrop-filter`, glow ou gradiente.

## Anexo C — Componentes `ui/` e DataTable

Convenção: props/emits em português; ícones só `lucide-vue-next` (16 md / 14 sm / 18 nav); alvos ≥ 36 px; nenhuma ação visível só em hover.

| Componente | API essencial |
|---|---|
| AppButton | `variante` primary/secondary/ghost/danger, `tamanho` sm(32)/md(36), `carregando`, `as` button/Link/a, slots `icone`/`iconeDireita` |
| IconButton | `icone`, `rotulo` (obrigatório → aria-label + tooltip), `tamanho` sm/md/lg, `variante` ghost/primary/danger |
| AppTooltip | componente com Teleport em `#camadas`, `aria-describedby`, hover e focus-visible, flip na borda |
| AppModal | sobre `<dialog>` (reaproveita lógica de `Components/Modal.vue`), `tamanho` sm/md/lg/xl, `persistente`, foco inicial + retorno de foco, vira sheet abaixo de `sm` |
| ConfirmHost + `useConfirm()` | `await confirmar({ titulo, mensagem, detalhe?, rotuloConfirmar, tom: 'danger'|'primary', requerDigitacao? })` → boolean; foco inicial no Cancelar quando danger |
| ToastHost + `useToast()` | `sucesso/erro/aviso/info(msg, { descricao?, acao?: {rotulo, onClick}, duracao })`, aria-live, máx. 3, pausa no hover; 4 s sucesso, 7 s com ação, erro até fechar; fontes: watcher de `flash` + interceptor axios |
| FormField | `rotulo`, `obrigatorio`, `dica`, `erro` (string ou array), `id` via `useId()`; injeta `aria-invalid`/`aria-describedby` |
| AppInput / AppMoneyInput / AppSelect / AppTextarea / AppCheckbox / AppSwitch / AppDateInput | nativos estilizados; `AppInput` aceita `mascara` (v-maska interno) e `limite="nome|email|descricao|anotacao"` lendo `props.limites`; `AppMoneyInput` em modo número pt-BR |
| AppDropdown + AppDropdownItem | Teleport, `role=menu`, setas/Home/End/Esc, fecha ao navegar; submenu para "Mover para estágio ▸" |
| AppBadge | `tom` neutral/primary/ok/warn/danger/info ou `cor` (hex de estágio com a regra do Anexo B), `ponto` |
| AppAvatar, AppAlert, AppCard, SectionHeader, PageHeader (H1 + subtítulo + ações + migalhas), EmptyState (título + subtítulo + CTA), AppSkeleton/SkeletonRows/SkeletonKpi, AppTabs (`sincronizarUrl`), KpiCard (`contar` via `useContador`, `href`), StatBar (horizontal/vertical, tooltip, rótulo de valor), AppSpinner | — |
| AppPagination | `meta`, `opcoesPorPagina [25,50,100]`; numerada com elipse + "26–50 de 1.243" + seletor |
| FilterBar / FilterChips / DateRange / PeriodoSelector | busca com debounce 300 ms, chips (múltiplo ou único, com cor), intervalo com presets, segmented 7d/30d/mês atual/mês anterior/personalizado; nunca some durante loading; em mobile vira `<details>` "Filtros (n)" |

**DataTable** (`ui/DataTable.vue`): props `id` (prefs em `localStorage['ui.tabela.<id>']`), `colunas[{key, rotulo, ordenavel, alinhar, largura, ocultarAbaixo: sm|md|lg, formatar, primaria}]`, `linhas`, `chave`, `meta` (ou `null` = sem paginação), `ordenacao{por,dir}`, `selecionavel` + `selecionadas` (v-model), `totalResultados` (para "Selecionar todos os N resultados" → emite `selecionar-todos`), `carregando`, `erro`, `hrefDaLinha`, `vazio{titulo,descricao,icone}`, `densidadePadrao`, `cabecalhoFixo`. Emits `update:ordenacao|pagina|porPagina|selecionadas`, `selecionar-todos`, `linha-clique`, `tentar-novamente`. Slots `celula-<key>`, `acoes`, `acoes-em-massa`, `vazio`, `barra`. Comportamento: `<table>` semântico; toolbar com contagem, toggle de densidade (48/40 px) e menu de colunas (persistido); `thead` sticky dentro de wrapper com `max-height` a partir de `lg`; coluna primária sticky à esquerda em scroll horizontal; abaixo de `md` as colunas marcadas se escondem e a primária mostra sub-linhas; linhas com `tabindex=0`, Enter/Espaço, ↑/↓; botões de ordenação com `aria-sort` (asc → desc → padrão); checkbox tri-state e Shift+clique; seleção zera ao mudar página/filtro; skeleton quando não há linhas, opacidade .6 + `aria-busy` quando há.

**`useListagem({ rota, only, padrao, filtros })`**: lê a query da URL, expõe `estado`, `busca` (debounce), `carregando`, `aplicar()`, `ordenarPor()`, `irParaPagina()`, `porPagina()`, `limpar()`, `filtrosAtivos`; monta a query omitindo valores padrão e chama `router.get(route(rota), query, { preserveState, preserveScroll, replace, only })`; ressincroniza em `router.on('navigate')`. **`useListagemAxios(url, opts)`**: mesma superfície, `axios.get` com `AbortController`, aceita `{data, meta, sort}` ou array simples.

## Anexo D — Migrations (ordem)

| # | Arquivo (`2026_10_17_…`) | Conteúdo | Fase |
|---|---|---|---|
| 1 | `100001_campos_de_negocio_e_contato_em_usuarios` | `valor_estimado decimal(12,2)`, `valor_fechado decimal(12,2)`, `probabilidade tinyint`, `previsao_fechamento date`, `ultimo_contato_em`, `fechado_em` + backfill + índices `(tenant_id, previsao_fechamento)`, `(tenant_id, funil_id, estagio_id, ultimo_contato_em)`, `(tenant_id, fechado_em)` | 1 |
| 2 | `100002_fechado_em_em_projetos_e_probabilidade_em_estagios` | `projetos.fechado_em` + backfill por `status.is_won` + índice; `estagios.probabilidade_padrao tinyint null`; remapeamento da paleta de cores de estágio | 1 |
| 3 | `100003_indices_de_apoio` | `anotacaos(usuario_id, created_at)`, `tarefas(tenant_id, concluido, data_limite)`, `status(tenant_id, is_won, is_lost)` | 1 |
| 4 | `100004_ativo_em_users` | `ativo`, `desativado_em`, índice `(tenant_id, ativo)` | 1 |
| 5 | `100005_create_convites_table` | `tenant_id, email, papel, token_hash unique, convidado_por, expira_em, aceito_em, cancelado_em` | 1 |
| 6 | `100006_create_importacoes_table` | arquivo, cabeçalhos/amostra/mapeamento/opções json, status, contadores, erros json, mensagem_falha | 1 |
| 7 | `100007_morph_map_no_activity_log_e_perdas` | reescreve `subject_type`, `causer_type`, `perdivel_type` para aliases | 1 |
| 8 | `100008_remover_remember_token_de_usuarios_e_projetos` | dropColumn | 1 |
| 9 | `110001_normalizar_eventos_de_estagio_no_activity_log` | eventos com subject `EstagioHistorico` → subject lead + properties | 5 |
| 10 | `110002_drop_estagio_historicos` | drop; `down()` recria vazia | 5 |
| 11 | `110003_renomear_tabelas_de_anotacoes_e_anexos` | `anotacoes`, `projeto_anotacoes`, `projeto_anexos` (molde de `rename_tags_to_estagios`) | 5 |

## Anexo E — Feedback e confirmações

Padrão: título é pergunta com o nome do objeto; corpo diz a consequência e se é reversível; botão primário nomeia a ação (vermelho se destrutivo), secundário "Cancelar"; nunca "OK/Sim". Toast em frase com ponto final; sucesso 4 s, com Desfazer 7 s, erro até fechar. 422 de formulário é inline com foco no primeiro campo inválido; 422 de regra de domínio vira toast com a mensagem do backend. Erro de carga vira bloco com "Tentar de novo". Interceptor axios: 401 → login; 403 "Você não tem permissão para esta ação."; 404 "Registro não encontrado. Pode ter sido removido por outra pessoa." (em ação sobre lead: "Este lead não está mais na sua carteira."); 419 "Sua sessão expirou." + Recarregar; 422 com `errors` silencioso; 429 "Muitas tentativas. Aguarde um instante."; 5xx/rede "Não foi possível concluir. Tente novamente."

| Ação | Diálogo? | Mensagem |
|---|---|---|
| Criar/editar lead, anotação, projeto, tarefa, modelo, funil, estágio, motivo | Não (guarda "Sair sem salvar?" em edições) | "Lead cadastrado." + Abrir / "Dados salvos." |
| Mover para estágio aberto; reabrir | Não; toast + **Desfazer** | "“Nome” movido para “Estágio”." / "Lead reaberto em “Estágio”." |
| Mover para Perdido | `ModalMotivoPerda` é a confirmação; aviso de projetos abertos | "Perda registrada: Preço." |
| Mover para Ganho | `ConfirmarGanhoDialog` (valor fechado, data, aviso de projetos) / **Marcar como ganho** | "Negócio ganho: R$ 12.000." |
| Mover de funil | Modal próprio / **Mover** | "Movido para “Funil” › “Estágio”." |
| Transferir 1 lead | Diálogo com select / **Transferir** ; toast + Desfazer | "“Nome” transferido para “Pessoa”." |
| Transferir N leads | "Transferir N leads para “Pessoa”?" / saem da carteira atual; histórico mantido / **Transferir N leads** | "N leads transferidos para “Pessoa”." |
| Mover para a lixeira (1 ou N; lead ou projeto) | Sim, danger: "Mover “Nome” para a lixeira?" / some das listas e do pipeline, com projetos, tarefas, anotações e arquivos; restaurável na Lixeira / **Mover para a lixeira** ; toast + **Desfazer** | "“Nome” movido para a lixeira." |
| Restaurar | Não | "“Nome” restaurado." + Abrir |
| Apagar anotação / documento / tarefa / modelo | Sim: "Apagar …?" / não dá para desfazer / **Apagar** | "Anotação apagada." |
| Concluir tarefa | Não; toast + Desfazer; "Reabrir" disponível | "Tarefa concluída." |
| Aplicar modelos | Não | "N tarefas criadas a partir dos modelos." |
| Arquivar funil / estágio / motivo | Sim, com a consequência específica ("estágios arquivados junto; leads continuam visíveis até serem movidos" / "já usado em N perdas; o histórico não muda") | "Funil arquivado." |
| Tornar funil padrão | Leve: "Leads novos sem funil escolhido entram nele." / **Tornar padrão** | "“Funil” agora é o padrão." |
| Reordenar; coluna padrão do quadro | Não | "Ordem salva." / "Coluna padrão atualizada." |
| Equipe: convidar; reenviar | Não | "Convite enviado para e-mail. Vale por 7 dias." |
| Equipe: alterar papel (rebaixar) | "Mudar “Nome” de Admin para Vendedor?" / passa a ver só os próprios leads / **Alterar papel** | "Papel de “Nome” alterado." |
| Equipe: desativar | Danger: "Desativar “Nome”?" / perde o acesso agora; os N leads continuam na empresa; opção de transferir / **Desativar** | "“Nome” desativado." |
| Importar | Na prévia: "Importar N linhas?" / **Importar N linhas** | "Importação concluída: 1.180 criados, 40 atualizados, 15 pulados, 5 com erro." |
| Exportar | Diálogo curto de colunas/separador / **Exportar N leads** | download direto |
| Limpar uploads pendentes | Só com ≥3 arquivos | — |
| Sair sem salvar | "Sair sem salvar?" / as alterações se perdem / **Sair sem salvar** · **Continuar editando** | — |
| Logout | Não | — |

## Anexo F — Telas, fluxos, indicadores e microcopy

### F.1 Fluxos críticos (resumo)

- **Cadastro rápido** (quick-add do Pipeline): nome/e-mail/telefone → Enter; vai para a coluna; coluna perdida → modal de motivo; e-mail duplicado inline com link "abrir". **Completo** (`LeadFormModal`): Contato + Negócio + Responsável (view-all); "Salvar e abrir".
- **Transição de estágio** (DnD, menu do card, `Shift+←/→`, badge do hero, massa): aberto → direto com Desfazer; perdido → motivo; ganho → diálogo de ganho; reabrir → direto. Conflito entre dois usuários: 409/422 → quadro recarrega com toast "Este lead foi alterado por outra pessoa."
- **Transferência**: individual (hero, grade, card) e em massa (seleção ou filtro); destino só membros ativos, exceto o atual; transferir para si = "Já é o responsável"; tarefas seguem o lead.
- **Convite**: Equipe → Convidar (e-mail + papel com descrição) → e-mail "Carla convidou você para o CRM da Acme" → `/convites/{token}` → nome + senha → login automático → dashboard com "Bem-vindo à Acme. Comece pelo Pipeline." Erros: expirado/usado/e-mail já em outra empresa (único global).
- **Importação**: Arquivo → Mapeamento (padrões e duplicados) → Processando (polling; pode sair) → Resultado (tiles, erros, CSV de erros com `linha_original` e `erro`, "Ver leads importados"). Chave de duplicado = e-mail; telefone duplicado é aviso.
- **Exportação**: resultado filtrado atual ou seleção; cabeçalhos iguais aos aceitos na importação (ida e volta); vendedor exporta só o que vê.
- **Lixeira**: excluir com diálogo + Desfazer; restaurar devolve estágio e responsável; estágio arquivado nesse meio tempo → toast "O estágio original está arquivado; mova o lead."; responsável desativado → diálogo pede novo responsável; projeto de lead na lixeira → "Restaure o lead primeiro".
- **Tarefas**: concluir otimista com Desfazer; reabrir; nova tarefa inline com "Aplicar modelo ▾"; diálogo "Aplicar modelos" com datas calculadas; sem modelos → link para Configurações.

### F.2 Mobile e teclado

Grades: colunas marcadas somem abaixo de `md`; a coluna primária mostra sub-linhas; menu de ações sempre visível. Pipeline: colunas com scroll-snap, uma por vez; mover pelo menu do card. Modais viram sheet. Atalhos: `/` busca, `N` novo lead, `Esc` limpa seleção, ↑/↓ + Enter nas grades, `M` e `Shift+←/→` no card.

### F.3 Indicadores do Dashboard

| Tile | Natureza | Definição | Clique |
|---|---|---|---|
| Leads ativos | estado | leads visíveis em estágio `aberto`; sub "+ N ganhos/perdidos" | `/leads?situacao=ativos` |
| Novos no período | fluxo | `created_at` no período | `/leads?de=&ate=` |
| Ganhos no período | fluxo | nº e Σ `COALESCE(valor_fechado, valor_estimado)` dos leads com `fechado_em` no período e estágio `ganho` | `/leads?situacao=ganhos&de=&ate=` |
| Perdidos no período | fluxo | nº e Σ `perdas.valor` com `perdas.created_at` no período | `/relatorios/perdas?...` |
| Taxa de ganho | fluxo | ganhos ÷ (ganhos + perdidos); "—" se 0 | — |
| Previsão (forecast) | estado com janela | Σ `valor_estimado × probabilidade/100` dos leads abertos com `previsao_fechamento` no período; sub "N leads · R$ X sem ponderar · faltam N probabilidades · R$ Y em projetos abertos" | `/leads?situacao=ativos&previsao_de=&previsao_ate=` |

Gráficos sem biblioteca (`StatBar` em div/SVG): "Leads por estágio" (cor do estágio com rótulo) e, em Relatórios, "Ganhos por mês" (uma cor). Reavaliar biblioteca só se entrar série temporal múltipla (metas, backlog).

### F.4 Microcopy

Tom direto, segunda pessoa, imperativo nas ações, sem exclamação. Vocabulário fixo: **lead, pipeline, estágio, funil, projeto/proposta, tarefa, responsável, membro, Lixeira**. Nunca "usuário" para lead. Mensagem = o que aconteceu + o que fazer. Tooltips curtos: "Abrir no WhatsApp", "Transferir", "Sem telefone".

| Tela / estado | Título | Subtítulo | CTA |
|---|---|---|---|
| Dashboard (0 leads) | Sua empresa ainda não tem leads | Cadastre o primeiro ou traga sua planilha. | Novo lead · Importar |
| Dashboard tarefas | Nada vence hoje | Veja as próximas em Tarefas. | — |
| Leads (0) | Nenhum lead cadastrado ainda | Cadastre o primeiro ou importe uma planilha. | Novo lead · Importar |
| Leads (filtro) | Nenhum lead com esses filtros | Tente outro termo ou limpe os filtros. | Limpar filtros |
| Leads (vendedor sem carteira) | Você ainda não tem leads | Cadastre um lead ou peça a um gestor para transferir leads para você. | Novo lead |
| Pipeline sem estágios | Este funil não tem estágios | Configure os estágios para começar a mover leads. | Configurar funis |
| Pipeline coluna | Nenhum lead aqui | Arraste um lead ou clique em Adicionar. | — |
| Tarefas (atrasadas) | Nenhuma tarefa atrasada | Bom sinal. | — |
| Tarefas (pendentes) | Nenhuma tarefa pendente | Crie follow-ups a partir do perfil de um lead. | — |
| Perfil › Atividades | Nada registrado ainda | As ações neste lead aparecem aqui. | — |
| Perfil › Tarefas | Nenhuma tarefa | Crie um follow-up ou aplique um modelo. | Nova tarefa · Aplicar modelos |
| Perfil › Anotações | Nenhuma anotação | Registre o que foi conversado. | Nova anotação |
| Perfil › Projetos | Nenhuma proposta ainda | Crie um projeto quando houver negociação concreta. | Novo projeto |
| Perfil › Documentos | Nenhum documento | Arraste arquivos aqui ou selecione. | Selecionar arquivos |
| Relatórios | Nenhum dado no período | Amplie o período ou mude os filtros. | Últimos 30 dias |
| Perdas | Nenhuma perda no período | Isso é bom — ou ninguém registrou. | — |
| Funis (todos arquivados) | Todos os funis estão arquivados | Restaure um ou crie outro para o Pipeline funcionar. | Novo funil |
| Motivos de perda | Nenhum motivo disponível | Sem motivos, ninguém consegue registrar uma perda. | Novo motivo |
| Modelos de tarefa | Nenhum modelo | Crie um para aplicar follow-ups em dois cliques. | Novo modelo |
| Equipe › Convites / Inativos | Nenhum convite pendente / Ninguém desativado | — | Convidar membro / — |
| Lixeira | A lixeira está vazia | Leads e projetos excluídos aparecem aqui. | — |
| Importar › resultado sem erros | Tudo importado sem erros | — | Ver leads |

### F.5 O que sai do sistema e por quê

| O quê | Por quê |
|---|---|
| `Inicial.vue`, `Welcome.vue`, painel de marketing do Login/Register | números inventados e links mortos num produto B2B |
| Auth Breeze em inglês | única parte em inglês e em outro tema |
| Toggle Lista/Pipeline, grade e modal no Dashboard | sidebar navega; `/leads` assume a grade |
| Donut "taxa de conversão" (lead com projeto) | contava projeto perdido como conversão |
| Engrenagem flutuante e ícone solto de mover funil no Kanban | escondidos no fim do scroll; viram itens de menu |
| Funil/estágio no formulário "Dados" | misturava edição cadastral com transição de negócio |
| Confirmação ao concluir tarefa | texto falso ("não pode ser desfeita"); fluxo mais frequente do vendedor |
| `sweetalert2`, `confirm()`, `mask`, Figtree, `@import` de fontes por página, `Maska.default` | duplicação, cores cravadas do tema escuro, dependências mortas ou quebradas |
| `GET /api/user`, `/api/timeline`, `/api/estagios` sem funil no Perfil/Dashboard, `user_id` em `tarefa-padroes` | sem consumidor ou redundantes |
| `Userarios`, `arquivo`, `Statu`, `estagio_historicos`, `remember_token` em leads, `Notifiable`, relações mortas, `verified` | legado |

### F.6 Backlog pós-plano (priorizado)

1. Notificações in-app e por e-mail (lembrete de tarefa, lead transferido, importação concluída) — L10.
2. Tarefas com hora, tipo e responsável próprio — L9.
3. Metas por vendedor e ranking na tabela "Por vendedor" — L11.
4. Detecção de duplicados (e-mail/telefone/nome) com merge — L16.
5. Funil de conversão por passagem de estágio (modelado sobre o activity log).
6. Campos personalizados e tags — L5.
7. Empresas/contatos separados — L3.
8. Modelos de mensagem e WhatsApp Cloud API com registro na timeline — L7.
9. Captura por formulário/webhook — L13.
10. LGPD: exclusão definitiva com anonimização, base legal, retenção — L17.
11. `MustVerifyEmail`, expurgo automático da lixeira, modelos de tarefa por empresa, PWA (L15), paleta de comandos `Ctrl+K`.
