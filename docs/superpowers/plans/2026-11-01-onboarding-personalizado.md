# Primeiro acesso personalizado — Plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Questionário de 4 passos no primeiro acesso de quem cria a empresa, que monta o sistema (estágios, motivos, modelos de tarefa, origens, rótulo do lead, visão do Dashboard, usuários sugeridos), seguido de boas-vindas e de um passo a passo guiado que pode ser cancelado no X.

**Architecture:**
- **Backend:** as respostas ficam em `perfis_de_empresa`. Um serviço (`PersonalizarEmpresa`) aplica tudo numa transação, e um middleware leva o admin ao questionário enquanto a empresa não responder nem pular. O estado do passo a passo fica em colunas de `users` e muda por `PATCH /api/tour`.
- **Front:**
  - um composable de módulo (`useTour`) lê o estado de `auth.user` e segue uma lista declarativa de passos (`passos.js`);
  - um host (`TourHost`), na casca autenticada, desenha o escurecimento, o recorte e o balão ao redor de `[data-tour="…"]`;
  - as telas avisam as ações com `useTour().avancar('evento')`.

**Tech Stack:** Laravel 11, Inertia 2, Vue 3 (`<script setup>`), Tailwind 3 com os tokens de `resources/css/theme.css`, PHPUnit (banco `CRMLeader_test`), Vitest + @vue/test-utils.

**Spec:** `docs/superpowers/specs/2026-11-01-onboarding-personalizado-design.md`. Os textos exatos das perguntas, opções, estágios, modelos e balões estão lá; este plano os referencia.

## Global Constraints

- Nada de commit nem push: o usuário pediu para acompanhar as alterações. Os passos "Commit" deste plano viram "Sem commit".
- Trabalhar no diretório principal, em `master`, sem worktree.
- Migração no banco de dev só com `php artisan migrate --path=database/migrations/<arquivo>`. Nada de `db:seed`, nem de comando destrutivo no dev. Os testes usam `CRMLeader_test`.
- Nenhuma biblioteca nova de front (nada de driver.js, shepherd etc.). O posicionamento usa `resources/js/utils/posicionar.js`.
- Visual: componentes de `resources/js/Components/ui/` e tokens (Poppins, raios 12/20/28, pílulas, claro e escuro). Cores das telas sempre por token, nunca hex no `.vue`.
- Toda rota de escrita autorizada (`$this->authorize`, `can:` ou exceção justificada em `tests/Feature/Autorizacao/TodaRotaDeEscritaAutorizaTest.php`).
- Multi-tenant: nada atravessa empresas. Consultas por `tenant_id` ou modelos com `BelongsToTenant`.
- O botão de tema (`AlternarTema`) só aparece para quem não está logado.
- Antes de dar por pronto: `php artisan test`, `npx vitest run`, `npm run build` e `vendor/bin/pint --test` verdes.

## Review Focus

1. **Recarregar a página no meio do passo a passo:** volta ao mesmo passo, não ao início e não some. Testado em `tests/js/tour/useTour.test.js`, no caso "estado vindo do servidor retoma o passo gravado".
2. **Alvo ausente** (menu lateral escondido no celular, sem permissão, tela ainda carregando): o balão aparece centralizado com Entendi, e nunca trava. Testado em `tests/js/tour/TourHost.test.js`, no caso "sem alvo, balão centralizado com Entendi".
3. **Empresa que já tem leads** (o admin pulou e respondeu depois, ou empresa antiga): a personalização não troca os estágios. Testado em `PersonalizarEmpresaTest`, no caso `test_com_lead_no_funil_os_estagios_nao_mudam`.
4. **Abrir `/boas-vindas` de novo ou enviar o questionário duas vezes:** redireciona ao Dashboard e não aplica de novo (não duplica modelos de tarefa nem motivos). Testado em `QuestionarioTest`, no caso `test_responder_de_novo_nao_reaplica`.
5. **Convidado ou vendedor numa empresa ainda sem perfil:** nunca é levado ao questionário, e o convidado recebe a versão curta do passo a passo. Testado em `QuestionarioTest` (`test_vendedor_nao_e_levado_ao_questionario`) e em `TourTest` (`test_convite_aceito_comeca_tour_curto_pendente`).

---

### Task 1: Esquema, modelo e configuração do onboarding

**Files:**
- Create: `database/migrations/2026_11_01_200001_onboarding_do_primeiro_acesso.php`
- Create: `app/Models/PerfilDeEmpresa.php`
- Create: `config/onboarding.php`
- Modify: `app/Models/Tenant.php` (relação `perfil()`), `app/Models/Usuario.php` (`origem` no `$fillable`)
- Test: `tests/Feature/Onboarding/EsquemaDoOnboardingTest.php`

**Interfaces:**
- Produces:
  - `perfis_de_empresa`: `id`, `tenant_id` (único, FK em tenants com cascade), `respostas` (json, nulo), `respondido_em`, `pulado_em`, `respondido_por` (FK em users, nullOnDelete), `telefone` (string 20, nulo), `aceita_dicas_whatsapp` (bool, padrão false), `aceite_dicas_em`, timestamps.
  - `users`: `tour_estado` (string 12, padrão `'pendente'`), `tour_versao` (string 8, padrão `'completa'`), `tour_passo` (string 40, nulo), `dashboard_visao` (string 8, nulo).
  - `usuarios.origem` (string 60, nulo).
  - Backfill na mesma migração: todo usuário existente fica com `tour_estado = 'concluido'`. As empresas existentes **não** ganham linha em `perfis_de_empresa`: só o cadastro novo cria a linha pendente (Task 3), e o middleware só age quando ela existe e está pendente. Assim, empresas antigas e as criadas pelos testes atuais nunca são levadas ao questionário.
  - `App\Models\PerfilDeEmpresa`: `$table = 'perfis_de_empresa'`; casts `respostas => array`, `respondido_em`, `pulado_em` e `aceite_dicas_em => datetime`, `aceita_dicas_whatsapp => boolean`. Métodos `resolvido(): bool` (respondido ou pulado), `static daEmpresa(int $tenantId): ?self` e `static pendente(int $tenantId): bool` (true só se a linha existe e não está resolvida).
  - `Tenant::perfil(): HasOne`.
  - `config('onboarding')` com as chaves `experiencias`, `cargos`, `ramos`, `tamanhos`, `para_quem`, `canais`, `origens` e `conheceu`, no formato abaixo. Os valores vêm da spec, seção "Perguntas, respostas e o que cada uma muda", e das tabelas de ramos e canais.

```php
'experiencias' => ['varios' => 'Já usei vários CRMs e conheço bem', 'alguns' => '…', 'nunca' => '…'],
'cargos'       => ['dono' => 'Dono(a) ou sócio(a)', 'diretor' => …, 'gestor' => …, 'vendedor' => …, 'marketing' => …, 'outro' => 'Outro'],
'ramos'        => ['agencia' => ['rotulo' => 'Agência (marketing, publicidade, digital)', 'estagios' => ['Em captação','Briefing','Proposta enviada','Negociação'], 'ganho' => 'Ganho', 'perdido' => 'Perdido', 'motivos' => [/* até 2 */]], …, 'outro' => ['rotulo' => 'Outro', 'estagios' => null]],
'tamanhos'     => ['1' => ['rotulo' => '1 pessoa', 'minimo' => 1], …, '6-10' => ['rotulo' => '6 a 10', 'minimo' => 6], …, '51+' => ['rotulo' => 'Mais de 50', 'minimo' => 51]],
'para_quem'    => ['empresas' => ['rotulo' => 'Empresas', 'nome_do_lead' => 'Nome da empresa'], 'pessoas' => [… 'Nome do cliente'], 'ambos' => ['rotulo' => 'Empresas e pessoas', 'nome_do_lead' => 'Nome']],
'canais'       => ['whatsapp' => ['rotulo' => 'WhatsApp', 'modelo' => ['Mandar WhatsApp de acompanhamento', 2]], …, 'reuniao_online' => […, 'modelo' => ['Confirmar a reunião', 1]], 'reuniao_presencial' => […, 'modelo' => ['Confirmar a reunião', 1]], 'outro' => ['rotulo' => 'Outro', 'modelo' => null]],
'origens'      => ['indicacao' => 'Indicação', 'site' => 'Site', 'redes_sociais' => 'Redes sociais', 'anuncios' => 'Anúncios (tráfego pago)', 'prospeccao' => 'Prospecção ativa', 'eventos' => 'Eventos e feiras', 'marketplace' => 'Marketplace', 'outro' => 'Outro'],
'conheceu'     => ['google' => 'Google', 'instagram' => …, 'linkedin' => …, 'youtube' => …, 'indicacao' => …, 'anuncio' => …, 'evento' => …, 'outro' => 'Outro'],
```

- [ ] **Step 1: Write the failing test.** Arquivo `EsquemaDoOnboardingTest`, com `RefreshDatabase` e `CenarioDeTenant`:
  - `test_usuario_novo_nasce_com_tour_pendente_completo`: `agente($t)->fresh()` tem `tour_estado === 'pendente'`, `tour_versao === 'completa'` e `tour_passo === null`.
  - `test_perfil_resolvido_quando_respondido_ou_pulado`: um perfil sem datas dá `resolvido() === false`; com `pulado_em` ou com `respondido_em`, `true`.
  - `test_config_cobre_todos_os_ramos_da_spec`: as chaves de `config('onboarding.ramos')` são exatamente as 11 da spec; todo ramo, exceto `outro`, tem de 3 a 4 estágios abertos e no máximo 2 motivos.
- [ ] **Step 2: Run it to see it fail.** `php artisan test --filter=EsquemaDoOnboardingTest`. Expected: FAIL (tabela, coluna ou classe inexistente).
- [ ] **Step 3: Implement** a migração (com o backfill), o modelo, as relações e o config com todo o conteúdo da spec.
- [ ] **Step 4: Run it to see it pass.** `php artisan test --filter=EsquemaDoOnboardingTest`. Expected: PASS.
- [ ] **Step 5: Apply to dev.** `php artisan migrate --path=database/migrations/2026_11_01_200001_onboarding_do_primeiro_acesso.php`. Expected: `DONE`. Depois, conferir no tinker que nenhum usuário ficou com `tour_estado = 'pendente'`.
- [ ] **Step 6:** Sem commit.

### Task 2: Serviço que personaliza a empresa

**Files:**
- Create: `app/Services/Onboarding/PersonalizarEmpresa.php`
- Create: `app/Services/Onboarding/PreferenciasDaEmpresa.php`
- Test: `tests/Feature/Onboarding/PersonalizarEmpresaTest.php`

**Interfaces:**
- Consumes: `PerfilDeEmpresa` e `config('onboarding')` (Task 1).
- Produces:
  - `PersonalizarEmpresa::aplicar(Tenant $tenant, User $quem, array $respostas): PerfilDeEmpresa`. É idempotente: se o perfil já está resolvido, devolve o perfil sem mexer em nada.
  - `PersonalizarEmpresa::pular(Tenant $tenant, User $quem): PerfilDeEmpresa`.
  - As chaves de `$respostas` são `experiencia`, `cargo`, `ramo`, `tamanho`, `para_quem`, `canais[]`, `origens[]`, `conheceu?`, `telefone?` e `dicas_whatsapp` (bool).
  - `PreferenciasDaEmpresa` (estáticos, por `int $tenantId`):
    - `origens(int $tenantId): array<string>`: os rótulos das origens escolhidas mais "Outro"; sem resposta, todos os rótulos do config;
    - `rotuloDoNome(int $tenantId): string`: "Nome da empresa", "Nome do cliente" ou "Nome";
    - `usuariosSugeridos(int $tenantId): ?int`: o `minimo` da faixa, ou null;
    - `equipeComMaisDeUm(int $tenantId): bool`: pela resposta; sem resposta, por usuários ativos + convites pendentes acima de 1;
    - `versaoDoTour(array $respostas): string`: `'curta'` para `varios` ou `alguns`, senão `'completa'`.

- [ ] **Step 1: Write the failing tests:**
  - `test_ramo_software_troca_os_estagios_do_funil_padrao`: depois de `aplicar` com `ramo = software`, os estágios não arquivados do funil `is_default`, por `ordem`, têm as descrições `['Lead','Demonstração','Período de teste','Proposta','Ganho','Perdido']`. Os tipos são `aberto` ×4, `ganho` e `perdido`. O ganho usa a cor `#1a7544` e o perdido, `#b42318`.
  - `test_com_lead_no_funil_os_estagios_nao_mudam`: com um lead criado antes, as descrições continuam as do `TenantBootstrapper`.
  - `test_ramo_outro_mantem_o_funil_padrao`.
  - `test_motivos_do_ramo_entram_sem_duplicar`: os 7 motivos de hoje mais os do ramo; aplicar de novo não duplica.
  - `test_modelos_de_tarefa_seguem_os_canais_no_maximo_quatro`: com `canais = ['whatsapp','ligacao','email','reuniao_online','visita']`, `$quem` fica com exatamente 4 `TarefaPadrao`, nesta ordem e com estes prazos: "Mandar WhatsApp de acompanhamento" (2), "Ligar para retomar o contato" (3), "Enviar e-mail com a proposta" (1), "Confirmar a reunião" (1). Os canais `reuniao_online` e `reuniao_presencial` juntos geram um modelo só.
  - `test_vendedor_fica_com_dashboard_meus_e_tour_conforme_experiencia`: com `cargo = vendedor` e `experiencia = varios`, `$quem` fica com `dashboard_visao = 'meus'` e `tour_versao = 'curta'`; com `cargo = dono` e `experiencia = nunca`, `'equipe'` e `'completa'`.
  - `test_perfil_grava_respostas_telefone_e_aceite`: `respondido_em`, `respondido_por`, `telefone`, `aceita_dicas_whatsapp = true` e `aceite_dicas_em` preenchidos.
  - `test_preferencias_da_empresa`: `rotuloDoNome` dá "Nome da empresa" para `empresas`; `origens` dá `['Indicação','Site','Outro']` para `['indicacao','site']`; `usuariosSugeridos` dá 6 para `6-10` e 51 para `51+`; sem perfil, `origens` traz todos os rótulos e `usuariosSugeridos` é `null`.
  - `test_pular_marca_resolvido_sem_mudar_nada`.
- [ ] **Step 2: Run them to see them fail.** `php artisan test --filter=PersonalizarEmpresaTest`. Expected: FAIL (classe inexistente).
- [ ] **Step 3: Implement.** Tudo numa `DB::transaction`:
  - Estágios só mudam se o funil padrão não tem leads, contando os da lixeira (`Usuario::withTrashed()`). Os antigos saem com `forceDelete`, e `users.kanban_default_estagio_id` que apontar para eles vira null.
  - As cores dos estágios abertos seguem, em ciclo, as do `TenantBootstrapper` (`#2b6cb0`, `#9c6612`, `#6b46c1`, `#b83280`).
  - Motivos e modelos de tarefa entram por `firstOrCreate` pela descrição ou título.
- [ ] **Step 4: Run them to see them pass.** Mesmo comando. Expected: PASS.
- [ ] **Step 5:** Sem commit.

### Task 3: Rotas, middleware e e-mail do questionário

**Files:**
- Create: `app/Http/Controllers/Web/BoasVindasController.php`, `app/Http/Requests/QuestionarioRequest.php`, `app/Http/Middleware/ExigirPerfilDaEmpresa.php`
- Modify:
  - `routes/web.php`;
  - `bootstrap/app.php` (anexar o middleware ao grupo `web`, depois de `ExigirAssinatura`);
  - `app/Http/Controllers/Auth/RegisteredUserController.php` (redirecionar a `boas-vindas.show`);
  - `app/Services/Assinaturas/AvisosDeAssinatura.php` (`perfilRespondido(PerfilDeEmpresa $p): void`);
  - `app/Mail/AvisoDeAssinaturaMail.php` (tipo `comercial_perfil`);
  - `resources/views/site/privacidade.blade.php`.
- Test: `tests/Feature/Onboarding/QuestionarioTest.php`

**Interfaces:**
- Consumes: `PersonalizarEmpresa` e `PerfilDeEmpresa` (Tasks 1 e 2).
- Produces:
  - `GET /boas-vindas` → `boas-vindas.show`, que renderiza a página Inertia `BoasVindas/Questionario` com as props `opcoes` (= `config('onboarding')`, só chaves e rótulos) e `nome` (o primeiro nome).
  - `POST /boas-vindas` → `boas-vindas.store`, que aplica e redireciona a `dashboard`.
  - `POST /boas-vindas/pular` → `boas-vindas.pular`, que pula e redireciona a `dashboard`.
  - As três ficam em `['auth','tenant','can:configuracoes.manage']`. Se a empresa não tem perfil pendente (já resolvido, ou empresa sem linha), as três redirecionam a `dashboard` sem fazer nada.
  - Validação em `QuestionarioRequest`:
    - obrigatórios: `experiencia`, `cargo`, `ramo`, `tamanho` e `para_quem` (`in:` as chaves do config);
    - `canais` e `origens`: `required|array|min:1`, itens `in:`;
    - opcionais: `conheceu` (`nullable|in:`), `telefone` (`nullable` + `TelefoneE164`) e `dicas_whatsapp` (`boolean`).
  - Mensagens em pt-BR, como "Informe o ramo de atividade da sua empresa.", "Escolha ao menos um canal." e "Escolha ao menos uma origem.".
  - O cadastro (`RegisteredUserController@store`), dentro da transação, cria a linha pendente: `PerfilDeEmpresa` só com `tenant_id`.
  - `ExigirPerfilDaEmpresa` redireciona a `boas-vindas.show` somente quando todas estas condições valem:
    - a requisição é GET, não espera JSON e não é `api/*`;
    - o usuário está logado, tem empresa e pode `configuracoes.manage` (chamar `setPermissionsTeamId($user->tenant_id)` antes de checar);
    - a rota não está entre as livres: `boas-vindas.*`, `logout`, `profile.*`, `password.*`, `verification.*`, `configuracoes.assinatura*` e `assinatura.suspensa`;
    - `PerfilDeEmpresa::pendente($user->tenant_id)`.
  - Depois de resolvido, o middleware guarda na sessão (`perfil_resolvido`) para não consultar o banco a cada página.
  - O e-mail `comercial_perfil` vai para `config('comercial.email')`, só quando o questionário é respondido (pular não envia). Assunto: "Novo cadastro: {empresa}". Corpo: o plano em teste, cada resposta como "Pergunta: rótulo", o telefone e o aceite de dicas.
  - Na `privacidade.blade.php`, o item "Dados que coletamos" ganha a frase sobre o questionário (a spec traz a lista de dados e as finalidades).

- [ ] **Step 1: Write the failing tests.** Arquivo `QuestionarioTest`, com `RefreshDatabase`, `CenarioDeTenant` e `Mail::fake()`. A empresa nova vem de `novoTenant()` + `TenantBootstrapper::bootstrap` (confira se `novoTenant()` já semeia) + `PerfilDeEmpresa` pendente, criado no teste.
  - `test_cadastro_leva_ao_questionario`: o POST em `register` (dados válidos) redireciona para `route('boas-vindas.show')` e cria a linha pendente.
  - `test_empresa_sem_linha_de_perfil_nunca_e_levada`: admin de `novoTenant()` sem perfil → `GET /dashboard` 200.
  - `test_admin_com_perfil_pendente_e_levado_ao_questionario`: o `GET /dashboard` do admin redireciona para `boas-vindas.show`; o `GET /configuracoes/assinatura` não redireciona (200).
  - `test_vendedor_nao_e_levado_ao_questionario`: o GET `/dashboard` do vendedor da mesma empresa responde 200.
  - `test_empresa_com_perfil_pulado_nao_e_levada`.
  - `test_responder_aplica_envia_email_e_vai_ao_dashboard`: POST com respostas válidas → redireciona para `dashboard`; o perfil fica com `respondido_em`; `Mail::assertSent` do tipo `comercial_perfil`, para o e-mail comercial, cujo corpo contém o nome da empresa e "Software e tecnologia".
  - `test_responder_de_novo_nao_reaplica`: o segundo POST não cria `TarefaPadrao` além dos do primeiro, e `GET /boas-vindas` redireciona para `dashboard`.
  - `test_validacao_em_portugues`: POST vazio → 302 com erros em `ramo` ("Informe o ramo de atividade da sua empresa."), `canais` e `origens`.
  - `test_pular_resolve_sem_email`: `Mail::assertNothingSent()` para o tipo `comercial_perfil`.
  - `test_vendedor_nao_responde`: o POST de um vendedor em `boas-vindas.store` dá 403.
- [ ] **Step 2: Run them to see them fail.** `php artisan test --filter=QuestionarioTest`. Expected: FAIL (rota inexistente).
- [ ] **Step 3: Implement** o controller, o request, o middleware (registro em `bootstrap/app.php`), o redirecionamento do cadastro, o e-mail e a frase da política. A política só ganha o trecho. O dono revisa o texto antes de publicar; avisar no relatório final.
- [ ] **Step 4: Run them to see them pass,** e rodar também `TodaRotaDeEscritaAutorizaTest` e os testes de cadastro existentes (`--filter=Registration`). Expected: PASS.
- [ ] **Step 5:** Sem commit.

### Task 4: Tela do questionário

**Files:**
- Create: `resources/js/Pages/BoasVindas/Questionario.vue`
- Modify: `resources/js/Layouts/AuthLayout.vue` (prop `largo`, que deixa o cartão centrado com até `max-w-[640px]`, e o `AlternarTema` só sem `usePage().props.auth?.user`)
- Test: `tests/js/pages/boas-vindas/Questionario.test.js`; `tests/js/layouts/AuthLayout.test.js` (+1 caso)

**Interfaces:**
- Consumes: as props `opcoes` e `nome`, e as rotas `boas-vindas.store` e `boas-vindas.pular` (Task 3).
- Produces: a página usa `useForm` com os campos da Task 2 e faz `form.post(route('boas-vindas.store'))`; "Pular e usar o padrão" faz `router.post(route('boas-vindas.pular'))`.

- [ ] **Step 1: Write the failing tests.** Inertia com mock, como em `Assinatura.test.js`.
  - `mostra o passo 1 de 4 com experiência (cartões) e cargo`: o texto contém "Passo 1 de 4", "Qual sua experiência com CRM?" e as 3 opções.
  - `Prosseguir sem responder mostra o erro e não avança`: clicar em Prosseguir com o passo vazio mostra a mensagem do campo e continua no passo 1.
  - `navega até o passo 4, volta com Voltar e envia tudo ao concluir`: preencher os 4 passos e clicar em "Concluir" chama `formPost` com `route('boas-vindas.store')`; o payload tem `canais` e `origens` como arrays.
  - `chips de canais e origens aceitam várias escolhas`.
  - `Pular e usar o padrão chama a rota de pular`.
  - Em `AuthLayout.test.js`: `logado, o botão de tema não aparece`, com `usePage` devolvendo `auth.user`.
- [ ] **Step 2: Run them to see them fail.** `npx vitest run tests/js/pages/boas-vindas tests/js/layouts/AuthLayout.test.js`. Expected: FAIL.
- [ ] **Step 3: Implement.** Usar `AuthLayout largo` (variante centrada) e reaproveitar os componentes existentes:
  - cartões de opção como botões `role="radio"` (raio 12, borda `line`; escolhido: `border-primary bg-primary-subtle`);
  - `FilterChips`, para os chips únicos e múltiplos;
  - `AppSelect`, para as listas;
  - `AppInput` com a máscara de telefone de `LeadFormModal`;
  - `AppCheckbox`.
- [ ] **Step 4: Run them to see them pass.** Mesmo comando. Expected: PASS.
- [ ] **Step 5:** Sem commit.

### Task 5: Origem do lead e rótulo do nome

**Files:**
- Modify:
  - `app/Support/Leads/RegrasDeLead.php` (`'origem' => ['nullable','string','max:60']`);
  - `app/Http/Middleware/HandleInertiaRequests.php` (`empresa` como closure: `['rotulo_nome_lead' => …, 'origens' => …, 'equipe_maior_que_um' => …]`, vindo de `PreferenciasDaEmpresa`, só com usuário logado);
  - `resources/js/Components/leads/LeadFormModal.vue`, `resources/js/Components/leads/AbaDados.vue` e o quick-add de `resources/js/Pages/Kanban.vue` (rótulo do nome);
  - `resources/js/Pages/Leads/Index.vue` (coluna `origem`, `ocultarAbaixo: 'xl'`);
  - a listagem que monta as linhas da grade de Leads, para incluir `origem`;
  - `app/Http/Controllers/Api/LeadController.php` (`exportar`: coluna `origem` depois de `previsao_fechamento`);
  - `app/Services/Importacao/CamposDeImportacao.php` (`'origem' => ['origem','fonte','canal','comoconheceu']`) e `app/Jobs/ImportarLeads.php` (grava `origem`).
- Test: `tests/Feature/Onboarding/OrigemDoLeadTest.php`; `tests/js/ui/visual.test.js` (+1 caso)

**Interfaces:**
- Consumes: `PreferenciasDaEmpresa::origens/rotuloDoNome/equipeComMaisDeUm` (Task 2).
- Produces: a prop compartilhada `page.props.empresa = { rotulo_nome_lead: string, origens: string[], equipe_maior_que_um: bool }`. Os passos 7 da versão completa e 5 da curta usam `equipe_maior_que_um`.

- [ ] **Step 1: Write the failing tests:**
  - `test_lead_grava_e_devolve_origem` (POST da API de leads com `origem = 'Indicação'`);
  - `test_origem_acima_de_60_e_recusada`;
  - `test_exportacao_traz_origem` (cabeçalho e valor);
  - `test_importacao_mapeia_coluna_origem` (o cabeçalho "Origem" vira o campo `origem` em `CamposDeImportacao::sugerir`);
  - `test_props_da_empresa_seguem_o_perfil` (`assertInertia`: `empresa.rotulo_nome_lead === 'Nome da empresa'` e `empresa.origens` igual à resposta + "Outro").
  - Em Vitest, `LeadFormModal mostra o rótulo do nome e as origens da empresa`: com `usePage` devolvendo `empresa`, o rótulo é "Nome da empresa" e o select de origem lista as opções.
- [ ] **Step 2: Run them to see them fail.** `php artisan test --filter=OrigemDoLeadTest` e `npx vitest run tests/js/ui/visual.test.js`. Expected: FAIL.
- [ ] **Step 3: Implement.** O select "Origem" usa `AppSelect` com placeholder "Selecione"; aparece no modal (seção Contato) e na aba Dados.
- [ ] **Step 4: Run them to see them pass,** junto com os testes existentes de exportação e importação (`--filter=Exportacao` e `--filter=Importa`). Expected: PASS.
- [ ] **Step 5:** Sem commit.

### Task 6: Visão do Dashboard e usuários sugeridos na Assinatura

**Files:**
- Create: `app/Services/Onboarding/VisaoDoDashboard.php`
- Modify: `app/Http/Requests/MetricasRequest.php` (regra `'visao' => ['nullable','in:equipe']`), `app/Http/Controllers/Web/DashboardController.php`, `resources/js/Pages/Dashboard.vue`, `app/Http/Controllers/Web/AssinaturaController.php` (prop `usuariosSugeridos`), `resources/js/Pages/Configuracoes/Assinatura.vue`
- Test: `tests/Feature/Onboarding/VisaoDoDashboardTest.php`; `tests/js/pages/configuracoes/Assinatura.test.js` (+1)

**Interfaces:**
- Produces: `VisaoDoDashboard::agente(MetricasRequest $r): ?User`, com estas regras:
  - sem `leads.view-all` → `$r->agente()`;
  - com `user_id` → `$r->agente()` e, se for o próprio usuário, grava `dashboard_visao = 'meus'`;
  - com `visao=equipe` → grava `'equipe'` e devolve null;
  - sem nada → o próprio usuário se a preferência for `'meus'`, senão null.
- No `Dashboard.vue`, escolher "Toda a equipe" faz a query levar `visao: 'equipe'`.
- O `DashboardController` usa o serviço no lugar de `$request->agente()`, e `periodo.user_id` reflete o resultado.
- No `Assinatura.vue`, o valor inicial de `assentos` vira `props.assinatura.assentos ?? Math.max(minimoDeAssentos, props.usuariosSugeridos ?? 0)`.

- [ ] **Step 1: Write the failing tests:**
  - `test_preferencia_meus_abre_o_dashboard_nos_proprios_leads` (`assertInertia periodo.user_id === eu->id`);
  - `test_escolher_toda_equipe_grava_a_preferencia` (GET com `visao=equipe` → `dashboard_visao === 'equipe'` e `periodo.user_id === null`);
  - `test_escolher_a_si_mesmo_grava_meus`;
  - `test_outro_vendedor_nao_muda_a_preferencia`;
  - `test_assinatura_sugere_usuarios_da_resposta` (prop `usuariosSugeridos === 6` para a faixa `6-10`).
  - Em Vitest, `Assinatura começa nos usuários sugeridos quando não há plano pago`.
- [ ] **Step 2: Run them to see them fail.** `php artisan test --filter=VisaoDoDashboardTest` e `npx vitest run tests/js/pages/configuracoes/Assinatura.test.js`. Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run them to see them pass,** junto com `--filter=Dashboard` e `--filter=Assinatura`. Expected: PASS.
- [ ] **Step 5:** Sem commit.

### Task 7: Estado do passo a passo no servidor

**Files:**
- Create: `app/Http/Controllers/Api/TourController.php`
- Modify:
  - `routes/api.php` (`PATCH /tour` → `api.tour.update`, no grupo autenticado);
  - `app/Services/Equipe/Convites.php` (`aceitar`: `tour_estado = 'pendente'`, `tour_versao = 'curta'`);
  - `app/Http/Controllers/Web/ConviteController.php` (tirar o `with('sucesso', …)`);
  - `tests/Feature/Autorizacao/TodaRotaDeEscritaAutorizaTest.php` (exceção `'PATCH api/tour'`, com o motivo "só altera o próprio usuário autenticado").
- Test: `tests/Feature/Onboarding/TourTest.php`

**Interfaces:**
- Produces: `PATCH /api/tour` com corpo `{acao: 'iniciar'|'avancar'|'concluir'|'cancelar'|'refazer', passo?: string}`. O `passo` é `required_if:acao,iniciar,avancar,refazer` e segue o padrão `/^[a-z0-9-]{1,40}$/`. As transições:
  - `iniciar` e `avancar`: `em_andamento` + passo;
  - `concluir`: `concluido`, passo null;
  - `cancelar`: `cancelado`, passo null;
  - `refazer`: `em_andamento`, `tour_versao = 'curta'` + passo.
- A resposta é `{tour_estado, tour_versao, tour_passo}`. Os três campos já chegam ao front em `auth.user`, sem mudança no share.

- [ ] **Step 1: Write the failing tests:**
  - `test_iniciar_avancar_e_concluir`;
  - `test_cancelar_limpa_o_passo`;
  - `test_refazer_usa_a_versao_curta`;
  - `test_passo_invalido_e_recusado` (422);
  - `test_convite_aceito_comeca_tour_curto_pendente` (o aceite pelo `ConviteController` deixa `tour_estado = 'pendente'` e `tour_versao = 'curta'`, e a sessão fica sem `sucesso`);
  - `test_so_altera_o_proprio_usuario` (outro usuário da mesma empresa não muda).
- [ ] **Step 2: Run them to see them fail.** `php artisan test --filter=TourTest`. Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run them to see them pass,** junto com `--filter=Convite` e `TodaRotaDeEscritaAutorizaTest`. Expected: PASS.
- [ ] **Step 5:** Sem commit.

### Task 8: Boas-vindas e passo a passo no front

**Files:**
- Create: `resources/js/Composables/useTour.js`, `resources/js/Components/tour/passos.js`, `resources/js/Components/tour/TourHost.vue`, `resources/js/Components/tour/BoasVindasModal.vue`
- Modify:
  - `resources/js/Layouts/AuthenticatedLayout.vue`: monta `<BoasVindasModal />` e `<TourHost />`; o menu da conta (desktop e gaveta) ganha "Refazer o passo a passo", com o ícone `RotateCcw`.
  - `data-tour` nos alvos:
    - `Kanban.vue`: botão Novo lead `novo-lead` e cada cartão `card-lead`;
    - `LeadFormModal.vue`: invólucro do campo nome `lead-nome`;
    - `Usuario/Perfil.vue`: botão Nova tarefa `nova-tarefa`;
    - `NavItens.vue`: `:data-tour="\`nav-${item.rota}\`"`;
    - `Dashboard.vue`: grade de KPIs `kpis`;
    - `Leads/Index.vue`: botão Importar `importar`.
  - Eventos:
    - `LeadFormModal.vue`: `avancar('lead-modal-aberto')` quando `aberto` vira true, e `avancar('lead-criado')` no `onSuccess`;
    - `TarefaFormModal.vue`: `avancar('tarefa-criada')` quando salva.
- Test: `tests/js/tour/useTour.test.js`, `tests/js/tour/TourHost.test.js`, `tests/js/tour/BoasVindasModal.test.js`

**Interfaces:**
- Consumes: `PATCH api.tour.update` (Task 7), `page.props.empresa.equipe_maior_que_um` (Task 5), `page.props.auth.permissions`.
- Produces:
  - `passos.js`: `export const PASSOS = { completa: Passo[], curta: Passo[] }`. Cada passo tem:
    - `chave`;
    - `rota?`: nome Ziggy; ao entrar no passo, se `route().current()` for outro, faz `router.visit`;
    - `alvo?`: valor de `data-tour`; null para o balão centralizado;
    - `titulo` e `texto`;
    - `avanca`: `{ evento }`, `{ rota }`, `'entendi'` ou `'concluir'`;
    - `quando?`: `(ctx) => bool`, onde `ctx = { pode, equipe }`.
  - As chaves da completa são `novo-lead`, `lead-nome`, `card-lead`, `nova-tarefa`, `tarefas`, `kpis`, `equipe` e `fim`, com textos e gatilhos da tabela "Versão completa" da spec. As da curta são `novo-lead`, `importar` (`quando: pode('leads.view-all')`), `tarefas`, `kpis`, `equipe` e `fim`, todas `'entendi'`, menos `fim`, que é `'concluir'`. Textos da curta:
    - `novo-lead`: "Seus leads entram por aqui." / "Cadastre um lead pelo botão ou arraste os cartões entre os estágios do funil.";
    - `importar`: "Traga sua planilha." / "Importe seus contatos de um arquivo CSV em poucos passos.";
    - os demais iguais aos da completa.
  - `useTour()`: devolve `{ ativo: Ref<bool>, pendente: Ref<bool>, passo: ComputedRef<Passo|null>, indice: ComputedRef<number>, total: ComputedRef<number>, iniciar(), avancar(evento: string), entendi(), concluir(), cancelar(), refazer() }`. O estado é de módulo, inicializado de `usePage().props.auth.user`. Os passos com `quando` falso são pulados. Cada transição chama `axios.patch(route('api.tour.update'), …)` sem esperar resposta (otimista). `avancar(evento)` só faz efeito se o passo atual esperar exatamente aquele evento.
  - `TourHost.vue`:
    - com `ativo`, procura `[data-tour="${passo.alvo}"]`, tentando de novo a cada 150 ms por até 3 s;
    - achou: desenha o escurecimento `fg-0/40` com recorte (4 painéis em volta do retângulo do alvo, mais 8 px de folga, deixando o alvo clicável) e o balão posicionado por `posicionar.js`;
    - não achou: balão centralizado com **Entendi**;
    - o balão é `role="dialog"` com título, texto, "N de T", X (`aria-label="Fechar o passo a passo"`) e, conforme `avanca`, Entendi ou Concluir;
    - Esc e X chamam `cancelar()`;
    - avanços por rota escutam `router.on('navigate')`;
    - reposiciona em `resize` e `scroll`.
  - `BoasVindasModal.vue`: `AppModal` aberto quando `pendente`, com o texto da spec; **Vamos lá!** chama `iniciar()`, e "Prefiro explorar sozinho", X ou Esc chamam `cancelar()`.

- [ ] **Step 1: Write the failing tests:**
  - `useTour.test.js`:
    - `estado pendente: iniciar vai ao primeiro passo e grava`;
    - `estado vindo do servidor retoma o passo gravado` (`tour_estado = 'em_andamento'` e `tour_passo = 'lead-nome'` → `passo.chave === 'lead-nome'`);
    - `avancar com o evento certo vai ao próximo; com outro evento não muda`;
    - `passo equipe é pulado sem equipe ou sem permissão`;
    - `cancelar desliga e grava cancelado`;
    - `refazer começa a versão curta`.
  - `TourHost.test.js`:
    - `com alvo, mostra o balão com título e N de T`;
    - `X cancela o passo a passo inteiro`;
    - `Entendi avança nos passos de mostrar`;
    - `passo de ação não tem Entendi`;
    - `sem alvo, balão centralizado com Entendi` (com fake timers, depois de 3 s).
  - `BoasVindasModal.test.js`:
    - `abre quando pendente; Vamos lá! inicia`;
    - `Prefiro explorar sozinho cancela`.
  - `AuthenticatedLayout.test.js`: `menu da conta tem Refazer o passo a passo`.
- [ ] **Step 2: Run them to see them fail.** `npx vitest run tests/js/tour tests/js/layouts`. Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run them to see them pass.** Mesmo comando. Expected: PASS.
- [ ] **Step 5:** Sem commit.

### Task 9: Verificação de ponta a ponta

**Files:** nenhum novo. Corrigir o que aparecer.

- [ ] **Step 1: Suítes.**
  - `php artisan test`: Expected tudo verde.
  - `npx vitest run`: Expected tudo verde.
  - `npm run build`: Expected `built`.
  - `vendor/bin/pint --test`: Expected `PASS`.
- [ ] **Step 2: Navegador (porta 8001).** Num navegador de teste, fazer um cadastro novo de ponta a ponta:
  - questionário com o ramo Software e "Nunca usei";
  - conferir os estágios no Pipeline;
  - Vamos lá! e os 8 passos;
  - criar "Cliente teste" e a tarefa.
  
  Repetir com "Já usei vários" e conferir a versão curta. Testar o X no meio, o recarregamento da página no passo 2 e a tela estreita (375 px). Não usar o navegador se o usuário estiver nele: perguntar antes.
- [ ] **Step 3: Limpeza.** A empresa criada no teste fica no dev. Informar o usuário, com o nome, sem apagar.
- [ ] **Step 4:** Sem commit. Relatar ao usuário com a lista de arquivos e o lembrete de revisar o texto da política de privacidade.
