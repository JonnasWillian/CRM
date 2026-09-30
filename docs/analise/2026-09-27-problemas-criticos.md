# Problemas críticos: corrigir antes de qualquer funcionalidade nova

**Data:** 2026-09-27
**Origem:** análise crítica do CRMLeader comparado a CRMs de mercado (ver [lacunas de mercado](2026-09-27-lacunas-de-mercado.md))
**Status:** resolvido em 2026-09-28 pelo plano [`docs/superpowers/plans/2026-09-27-correcao-problemas-criticos.md`](../superpowers/plans/2026-09-27-correcao-problemas-criticos.md).

Todas as evidências abaixo foram conferidas no código em 2026-09-27. Os IDs (P1…P8) são estáveis e devem ser usados no plano de melhorias.

## Resumo

| # | Problema | Severidade | Esforço | Prova |
|---|---|---|---|---|
| P1 | Anexos em disco público, acessíveis sem login | Crítica | Médio | `tests/Feature/Arquivos/DownloadProtegidoTest.php`, `GravacaoDeArquivoTest.php`, `CicloDeVidaDoArquivoTest.php` |
| P2 | Upload sem validação de tipo e tamanho | Crítica | Baixo | `tests/Feature/Arquivos/PoliticaDeUploadTest.php` |
| P3 | Excluir o perfil apaga todos os leads do vendedor | Crítica | Médio | `tests/Feature/Contas/ExclusaoDeContaTest.php`, `tests/Feature/DataIntegrity/SemCascataDestrutivaTest.php` |
| P4 | Excluir lead é definitivo, em cascata, e deixa órfãos | Alta | Médio | `tests/Feature/Exclusao/LixeiraDeLeadTest.php`, `LixeiraDeProjetoTest.php`, `SemCascataDestrutivaTest.php` |
| P5 | Telefone salvo como número inteiro | Alta | Baixo | `tests/Unit/TelefoneTest.php`, `tests/Feature/Leads/TelefoneDoLeadTest.php`, `NormalizacaoDeTelefonesTest.php` |
| P6 | Anotações limitadas a 255 caracteres sem validação | Média | Baixo | `tests/Feature/DataIntegrity/LimitesDeTextoTest.php` |
| P7 | Sem paginação: lista inteira de leads vai para o navegador | Alta | Médio/Alto | `tests/Feature/Leads/ListagemPaginadaTest.php`, `MetricasSemListaDeIdsTest.php`, `tests/Feature/ActivityLog/TimelinePaginadaTest.php`, `TimelineParityTest.php` |
| P8 | Problemas menores de robustez (vários) | Média | Baixo | `tests/Feature/Funis/PreferenciaDoKanbanTest.php`, `tests/Feature/Navegacao/VerbosDeLeituraTest.php`, `tests/Feature/Auth/LimiteDeRequisicoesTest.php` |


---

## P1. Anexos em disco público, sem separação por empresa e sem autenticação

**O que é:** os anexos de lead e de projeto são gravados no disco `public` do Laravel, dentro de `storage/app/public/arquivos/`, sem prefixo de tenant. O download é um link direto para `/storage/...`, servido pelo servidor web sem passar pelo Laravel.

**Onde:**
- [app/Service/ArquivoService.php:8](../../app/Service/ArquivoService.php#L8): `$arquivo->store('arquivos', 'public')`
- [resources/js/Pages/Usuario/Perfil.vue:589](../../resources/js/Pages/Usuario/Perfil.vue#L589): `` :href="`/storage/${arquivo.local}`" ``
- [resources/js/Pages/Usuario/ProjetoPanel.vue:670](../../resources/js/Pages/Usuario/ProjetoPanel.vue#L670): o mesmo padrão para anexos de projeto

**Impacto:** qualquer pessoa com a URL baixa o arquivo, sem login, inclusive usuários de outra empresa ou alguém de fora que recebeu o link. As policies `ArquivoPolicy` e `ProjetoAnexoPolicy` protegem a listagem, mas não o conteúdo. Para um CRM com contratos, documentos pessoais e propostas, isso é um vazamento de dados e um problema de LGPD.

**Direção de correção:**
- Gravar em disco privado com caminho particionado por tenant (`tenants/{tenant_id}/...`).
- Servir o download por uma rota autenticada que chama a policy (`Storage::download`) ou por URL temporária assinada.
- Migrar os arquivos existentes para o disco privado.

---

## P2. Upload sem validação de tipo e tamanho

**O que é:** as FormRequests de upload só exigem que exista um arquivo.

**Onde:**
- [app/Http/Requests/ArquivoRequest.php:56](../../app/Http/Requests/ArquivoRequest.php#L56): `'arquivo' => ['required', 'file']`
- [app/Http/Requests/ProjetoAnexoRequest.php:57](../../app/Http/Requests/ProjetoAnexoRequest.php#L57): mesma regra

**Impacto:**
- Aceita qualquer tipo de arquivo, como `.html`, `.svg` com script e executáveis. Combinado com P1, um `.html` enviado fica hospedado no mesmo domínio da aplicação, o que abre caminho para XSS armazenado e phishing.
- Sem limite de tamanho, é possível encher o disco.

**Direção de correção:** regras `mimes:`/`mimetypes:` com uma lista permitida (pdf, imagens, documentos Office, planilhas, txt, csv), `max:` em KB, e `Content-Disposition: attachment` no download.

---

## P3. Excluir o perfil apaga todos os leads do vendedor

**O que é:** a chave estrangeira `usuarios.user_id` (dono do lead) tem `ON DELETE CASCADE`, e a exclusão de perfil do Breeze faz exclusão definitiva do `User`.

**Onde:**
- [database/migrations/2025_01_28_161140_usuario.php:22](../../database/migrations/2025_01_28_161140_usuario.php#L22): `->onDelete('cascade')`
- [app/Http/Controllers/ProfileController.php:56](../../app/Http/Controllers/ProfileController.php#L56): `$user->delete()`
- [database/migrations/2026_07_11_000001_create_tarefa_padroes_table.php:13](../../database/migrations/2026_07_11_000001_create_tarefa_padroes_table.php#L13): o mesmo vale para os modelos de tarefa
- `User` não usa `SoftDeletes`

**Impacto:**
- Um vendedor que exclui a própria conta apaga todos os leads dele. Por causa da cascata de P4, apaga também projetos, notas, arquivos, tarefas e histórico. Isso é perda de dados da empresa causada por uma ação individual.
- Se o único admin se excluir, a empresa fica sem nenhum admin e sem como recuperar o acesso administrativo.

**Direção de correção:**
- Trocar a cascata por `restrict` ou por reatribuição obrigatória dos leads antes da desativação (depende de L2, transferência de leads).
- Usar exclusão lógica ou desativação do `User` em vez de exclusão definitiva.
- Impedir a exclusão do último admin da empresa.
- Reavaliar se o vendedor deve poder excluir a própria conta ou se isso cabe só ao admin.

---

## P4. Excluir um lead é definitivo, em cascata, e deixa dados órfãos

**O que é:** `Usuario` (lead) e `Projeto` não usam `SoftDeletes`. A exclusão do lead se propaga pelo banco.

**Onde:**
- [app/Http/Controllers/Userarios.php:216-223](../../app/Http/Controllers/Userarios.php#L216-L223): `$usuario->delete()`
- Cascatas a partir de `usuarios`:
  - [anotacao.php:18](../../database/migrations/2025_04_07_004542_anotacao.php#L18)
  - [arquivo.php:18](../../database/migrations/2025_05_04_193318_arquivo.php#L18)
  - [projeto.php:20](../../database/migrations/2026_04_24_010802_projeto.php#L20)
  - [create_tarefas_table.php:13](../../database/migrations/2026_07_08_000001_create_tarefas_table.php#L13)
  - [rename_tags_to_estagios.php](../../database/migrations/2026_09_22_100001_rename_tags_to_estagios.php) (histórico de etapas)
- `perdas` é polimórfica (`perdivel_type`/`perdivel_id`), sem chave estrangeira: [create_perdas_table.php:34](../../database/migrations/2026_09_26_100002_create_perdas_table.php#L34)

**Impacto:**
- Um clique errado apaga o lead e todo o histórico dele, sem lixeira e sem desfazer.
- Os arquivos físicos continuam no disco. Com P1, eles continuam acessíveis pela URL.
- Os registros de `perdas` e `activity_log` ficam apontando para um lead inexistente e distorcem o relatório "Por que perdemos".

**Direção de correção:**
- Adicionar `SoftDeletes` em `Usuario` e `Projeto`, com lixeira e restauração.
- Na exclusão definitiva (se existir, por exemplo para LGPD), apagar os arquivos físicos e decidir explicitamente o destino de `perdas` e `activity_log` (manter anonimizado ou remover).

---

## P5. Telefone salvo como número inteiro

**O que é:** a coluna `usuarios.telefone` é `bigInteger`, mas a validação trata o campo como texto. Só funciona porque o frontend remove tudo que não é dígito.

**Onde:** [database/migrations/2025_01_28_161140_usuario.php:19](../../database/migrations/2025_01_28_161140_usuario.php#L19): `$table->bigInteger('telefone')`

**Impacto:**
- Perde zeros à esquerda e o `+` de código de país, então não dá para guardar `+55 …` nem números internacionais.
- Bloqueia a integração com WhatsApp (L7), que exige o formato E.164 (`+5511999999999`).
- O campo é obrigatório, então não existe lead sem telefone.

**Direção de correção:** migrar para `string(20)` guardando o formato E.164 normalizado, com migração de dados que acrescenta `+55` aos números existentes, e revisar a obrigatoriedade.

---

## P6. Anotações limitadas a 255 caracteres, sem validação

**O que é:** a coluna `descricao` das anotações é `string` (VARCHAR 255), e a validação não tem `max`.

**Onde:**
- [database/migrations/2025_04_07_004542_anotacao.php:16](../../database/migrations/2025_04_07_004542_anotacao.php#L16) e [2026_05_02_191238_projeto_anotacao.php:16](../../database/migrations/2026_05_02_191238_projeto_anotacao.php#L16)
- [app/Http/Controllers/Userarios.php:246](../../app/Http/Controllers/Userarios.php#L246) e [:266](../../app/Http/Controllers/Userarios.php#L266): `'descricao' => 'required|string'`

**Impacto:** uma anotação com mais de 255 caracteres (um resumo de reunião, por exemplo) gera erro 500 de banco em vez de uma mensagem de validação. O MySQL em modo estrito recusa o texto; em modo não estrito, corta em silêncio.

**Direção de correção:** migrar as colunas para `text` e validar com `max:` coerente (por exemplo 10000), tanto em anotações de lead quanto de projeto.

---

## P7. Sem paginação: a lista inteira de leads vai para o navegador

**O que é:** a listagem de leads devolve todos os registros visíveis ao usuário, e busca, filtros e ordenação acontecem no cliente. A timeline antiga faz uma consulta por coleção relacionada, com N+1 dentro dos projetos.

**Onde:**
- [app/Http/Controllers/Userarios.php:30-40](../../app/Http/Controllers/Userarios.php#L30-L40): `Usuario::visibleTo(...)->...->get()`
- [app/Http/Controllers/Userarios.php:131-200](../../app/Http/Controllers/Userarios.php#L131-L200): `timeline()`, com um `foreach` de `ProjetoAnotacao` e `ProjetoAnexo` para cada projeto
- `resources/js/Pages/Dashboard.vue`: filtros de texto, etapa e data rodam sobre a lista completa

**Impacto:** com alguns milhares de leads, gestores e admins (que veem tudo) carregam megabytes de JSON a cada abertura do dashboard, e a tela fica lenta. O custo cresce linearmente com a base do cliente.

**Direção de correção:**
- Paginação e filtros no servidor (`paginate()` com parâmetros de busca, etapa, período e ordenação).
- Concluir a fase 4 do activity log (trocar a timeline antiga pelo endpoint paginado `/leads/{usuario}/atividades`, que já existe) e remover `timeline()`.

---

## P8. Problemas menores de robustez

| Item | Onde | Correção sugerida |
|---|---|---|
| `kanbanSettings()` não valida `default_estagio_id` (aceita etapa de outro funil, de outra empresa ou inexistente) e transforma qualquer exceção em 404 "Usuário não encontrado" | [Userarios.php:424-432](../../app/Http/Controllers/Userarios.php#L424-L432) | Validar `exists` com escopo de tenant e remover o `catch (\Exception)` |
| Excluir lead retorna `201 Created` | [Userarios.php:222](../../app/Http/Controllers/Userarios.php#L222) | Retornar `200` ou `204` |
| Rotas de leitura usando POST (`/pegarUsuarios`, `/projetos`, `/estagios`, `/status`, `/metricas`, `/kanban`, `/buscarArquivo`) | `routes/api.php` | Trocar para GET com query string (casa com P7) |
| Sem limite de requisições na API; só as rotas de verificação de e-mail têm `throttle` | [routes/auth.php:43](../../routes/auth.php#L43) | `throttle:api` no grupo `/api` e limite no login |
| `APP_DEBUG=true` no exemplo de ambiente | [.env.example:4](../../.env.example#L4) | Padrão `false`, com nota no README para desenvolvimento |
| Regras de perda e funil dependem de observers, e escritas via query builder (`update()` em massa) passam por fora deles | `app/Providers/AppServiceProvider.php` (comentário), `app/Observers/` | Centralizar as escritas nos services existentes e cobrir com testes |
| Frontend envia `user_id` em `/metricas` e `/tarefasPendentes`, e o backend ignora | `resources/js/Pages/Dashboard.vue` | Remover o parâmetro para não sugerir um controle que não existe |

---

## Dívida técnica relacionada (não bloqueia o lançamento)

- **Controller concentrando responsabilidades:** [Userarios.php](../../app/Http/Controllers/Userarios.php) tem 469 linhas e cuida de leads, anotações, kanban, métricas e timeline. O nome tem erro de digitação.
- **Nomes inconsistentes:**
  - `app/Http/Controllers/arquivo.php` e `app/Models/arquivo.php` em minúsculas, o que obrigou a registrar a policy manualmente;
  - modelo `Statu`;
  - tabelas `anotacaos`, `projetoAnotacaos` e `projetoAnexos`;
  - ~~pastas `app/Service` e `app/Services` convivendo~~ — **resolvido (Tarefa 2):** `app/Service` foi removida, só resta `app/Services`.
- **Vocabulário confuso:** `Usuario` significa lead e `User` significa vendedor. Vai piorar quando existirem contatos e empresas (L3).
- **Campo com nome errado:** `ProjetoAnexoRequest` lê o id do projeto de um campo chamado `usuario_id`.
- ~~**Activity log incompleto:** a fase 4 está pendente. [TimelinePanel.vue:75](../../resources/js/Pages/Usuario/TimelinePanel.vue#L75) ainda chama `/api/timeline`, e o endpoint novo não tem quem o use.~~ — **resolvido (Tarefa 16):** `TimelinePanel.vue` consome o endpoint paginado `/leads/{usuario}/atividades`; `timeline()` e `/api/timeline` continuam no código, **deprecados** e sem consumidor no front; a remoção fica para depois de a versão nova rodar em produção. Prova: `tests/Feature/ActivityLog/TimelinePaginadaTest.php`, `TimelineParityTest.php`.
- ~~**Docblock falso:** o docblock da migration `2026_09_20_162300_add_tenant_id_and_lead_id_to_activity_log_table.php` afirmava que nenhuma tabela filha de `usuarios` tinha FK para ela — falso.~~ — **resolvido (Tarefa 5):** docblock corrigido para descrever a FK real, sem alterar a instrução da migration.
- **Código morto:**
  - `resources/js/Pages/Welcome.vue` sem rota;
  - `Notifiable` sem uso em `Estagio`, `Projeto` e `Usuario`;
  - coluna `rememberToken` em `usuarios` e `projetos`;
  - permissão `agentes.manage` sem consumidor;
  - tabela `personal_access_tokens` inutilizável (falta `HasApiTokens`).
- **Retenção não agendada:** `activitylog:clean` não está no agendador, apesar da retenção de 365 dias configurada.
- **Infraestrutura:** sem Docker, sem monitoramento de erros, sem Pint ou análise estática no CI.
