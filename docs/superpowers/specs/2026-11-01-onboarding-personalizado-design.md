# Primeiro acesso: questionário que personaliza, boas-vindas e passo a passo

Data: 01/11/2026. Status: aguardando aprovação. Revisado por um leitor independente; as dúvidas dele estão resolvidas no texto.

## Por quê

Hoje quem se cadastra cai no Dashboard de uma empresa genérica: um funil "Vendas" com estágios que não são do ramo dela ("Em desenvolvimento", "Concluído", "Pausado"), nenhum modelo de tarefa e nenhuma orientação. A pessoa precisa descobrir sozinha onde cadastrar o primeiro lead e por que o CRM vale a pena.

O objetivo é que, em uns três minutos, a pessoa:

1. responda um questionário curto que **monta o sistema para a empresa dela** (estágios do funil, motivos de perda, modelos de tarefa, origens de lead, visão do Dashboard);
2. receba boas-vindas;
3. seja guiada pelo uso real: cadastra o primeiro lead, vê o funil, agenda a primeira tarefa.

Também serve para o dono do UserFlow conhecer quem está entrando: ramo, tamanho, cargo e como conheceu o produto.

## Metas

- O questionário aparece **uma vez**, para quem criou a empresa, no primeiro acesso depois do cadastro.
- As respostas mudam o sistema de verdade, e a pessoa vê isso no Pipeline logo em seguida.
- O passo a passo guia a pessoa fazendo, não só lendo: nos passos de ação, o balão espera a ação acontecer.
- Quem não quer ser guiado sai com um clique, e não é incomodado de novo.

## Fora do escopo

- Personalizar empresas que já existem. Elas não veem o questionário nem o passo a passo (veja Migração).
- Editar as respostas depois. O que elas montam (funil, motivos, modelos) já é editável nas telas de Configurações.
- Passo a passo para o site público.
- Notificações, e-mail de sequência de boas-vindas e dicas automáticas pelo WhatsApp. O opt-in fica gravado para uso futuro; nada é enviado agora.

## O que seria um fracasso, mesmo funcionando

- O questionário ou o passo a passo reaparecer para quem já respondeu, cancelou ou já usava o sistema.
- O passo a passo travar a pessoa: balão apontando para algo que não existe, sem como sair.
- A personalização apagar dados da pessoa. Ela só substitui o que o cadastro acabou de criar e ainda não foi usado.
- Visual destoando do resto. O questionário e os balões usam os componentes e tokens atuais (Poppins, cantos 12/20/28, pílulas, claro e escuro).

## Quem vê o quê

| Pessoa | Questionário | Boas-vindas e passo a passo |
|---|---|---|
| Quem se cadastrou e criou a empresa | Sim, no primeiro acesso | Sim, logo depois do questionário |
| Quem entrou por convite | Não (a empresa já está configurada) | Sim, no primeiro acesso, na **versão curta** |
| Usuários e empresas que já existem hoje | Não | Não (só se escolherem "Refazer o passo a passo", e então na **versão curta**) |

A versão completa, que pede para cadastrar um lead e uma tarefa de verdade, só roda para quem criou a empresa, logo depois do questionário, com a empresa ainda vazia. Quem chega a uma empresa que já está em uso recebe a curta, que só mostra os lugares, para não criar "Cliente teste" no funil que a equipe toda vê.

## Parte 1: o questionário

### Fluxo

- O cadastro (`RegisteredUserController@store`) passa a redirecionar para `/boas-vindas` em vez de `/dashboard`.
- Enquanto a empresa não tiver o questionário respondido ou pulado, o admin que acessar qualquer página do app é levado a `/boas-vindas`. Rotas de conta, logout, assinatura e API não são bloqueadas.
- São 4 passos curtos, com o rótulo "Passo N de 4", **Voltar** e **Prosseguir**, e **Concluir** no último. Os erros aparecem abaixo de cada pergunta ("Informe o ramo de atividade da sua empresa."). As respostas de cada passo são validadas antes de avançar, no cliente, e todas de novo no servidor ao concluir.
- Em todos os passos há um link discreto **"Pular e usar o padrão"**. Ele marca o questionário como pulado, **de vez**: o questionário não volta, a empresa fica com o funil padrão de hoje, o passo a passo usa a versão completa e o e-mail ao comercial não é enviado. O texto do link diz isso para não prometer uma volta que não existe.
- Ao concluir, o servidor aplica a personalização numa transação e redireciona para o Dashboard, onde as boas-vindas abrem.
- A tela é limpa: sem a barra lateral, com a marca no topo, o fundo de entrada (`--fundo-entrada`) e um cartão largo (até ~640px), no estilo das telas de login. Funciona no celular.

### Perguntas, respostas e o que cada uma muda

As opções e os modelos ficam em `config/onboarding.php`, para ajustar sem mexer em código.

| Passo | Pergunta | Tipo | Obrigatória | Opções | O que muda |
|---|---|---|---|---|---|
| 1 | Qual sua experiência com CRM? | escolha única (cartões) | sim | Já usei vários CRMs e conheço bem · Já usei um ou outro no dia a dia · Nunca usei ou uso pouco | Versão do passo a passo: as duas primeiras levam à **curta**, a última à **completa** |
| 1 | Qual seu cargo? | lista | sim | Dono(a) ou sócio(a) · Diretor(a) comercial · Gestor(a) de vendas · Vendedor(a) · Marketing · Outro | Vendedor(a) abre o Dashboard filtrado nos **próprios leads**; os demais, na **equipe toda** |
| 2 | Qual o ramo da sua empresa? | lista | sim | Agência (marketing, publicidade, digital) · Consultoria e assessoria · Software e tecnologia · Serviços profissionais (contabilidade, advocacia, engenharia) · Educação e cursos · Saúde e estética · Imobiliário · Indústria e distribuição · Varejo e e-commerce · Eventos e turismo · Outro | Estágios do funil padrão e motivos de perda (tabela abaixo) |
| 2 | Quantas pessoas da empresa vão usar o UserFlow? | chips de escolha única | sim | 1 · 2 · 3 · 4 · 5 · 6 a 10 · 11 a 20 · 21 a 50 · Mais de 50 | Com mais de 1, o passo a passo inclui "Convide sua equipe". Na Assinatura, o número de usuários começa em `max(ocupados, mínimo da faixa)` ("Mais de 50" conta como 51), sem limitar ao plano: planos menores mostram o aviso de limite que já existe. Só enquanto nenhum plano foi pago (`assentos` vazio) |
| 3 | Para quem você vende? | lista | sim | Empresas · Pessoas · Empresas e pessoas | Rótulo do nome no cadastro e na edição do lead: "Nome da empresa", "Nome do cliente" ou "Nome" |
| 3 | Como você fala com seus leads e clientes? | chips de múltipla escolha | ao menos 1 | WhatsApp · Ligação · E-mail · Reunião online · Reunião presencial · Visita · LinkedIn · Instagram ou Facebook · Outro | Modelos de tarefa criados para o admin, no canal certo (tabela abaixo) |
| 3 | De onde vêm seus leads? | chips de múltipla escolha | ao menos 1 | Indicação · Site · Redes sociais · Anúncios (tráfego pago) · Prospecção ativa · Eventos e feiras · Marketplace · Outro | Viram as opções do novo campo **Origem** do lead |
| 4 | Como você conheceu o UserFlow? | lista | não | Google · Instagram · LinkedIn · YouTube · Indicação · Anúncio · Evento · Outro | Só informação |
| 4 | Qual seu celular ou WhatsApp? | telefone com máscara (padrão do sistema) | não | — | Só informação |
| 4 | Quero receber dicas pelo WhatsApp | caixa de seleção | não | — | Só informação (opt-in com data, para uso futuro) |

### Estágios do funil e motivos de perda por ramo

O funil padrão "Vendas", criado no cadastro, tem seus estágios **substituídos** pelos do ramo, desde que ainda não haja nenhum lead nele (no primeiro acesso, não há). Se houver, nada muda. Todo modelo termina com um estágio de ganho e um de perda. As cores vêm da paleta categórica atual (`--cat-*`), com `--ok` no ganho e `--danger` na perda, como o bootstrapper faz hoje.

| Ramo | Estágios abertos, na ordem | Ganho / Perdido |
|---|---|---|
| Agência | Em captação · Briefing · Proposta enviada · Negociação | Ganho · Perdido |
| Consultoria e assessoria | Primeiro contato · Diagnóstico · Proposta enviada · Negociação | Ganho · Perdido |
| Software e tecnologia | Lead · Demonstração · Período de teste · Proposta | Ganho · Perdido |
| Serviços profissionais | Contato · Reunião · Orçamento enviado · Negociação | Ganho · Perdido |
| Educação e cursos | Interessado · Contato feito · Aula experimental · Matrícula em andamento | Matriculado · Perdido |
| Saúde e estética | Contato · Avaliação agendada · Orçamento enviado | Fechado · Perdido |
| Imobiliário | Contato · Visita agendada · Proposta · Documentação | Fechado · Perdido |
| Indústria e distribuição | Prospecção · Cotação enviada · Negociação · Pedido em análise | Ganho · Perdido |
| Varejo e e-commerce | Contato · Atendimento · Orçamento | Venda feita · Perdido |
| Eventos e turismo | Contato · Orçamento enviado · Negociação | Fechado · Perdido |
| Outro | Mantém o funil padrão de hoje | — |

Os motivos de perda também seguem o ramo: os 7 de hoje mais até 2 do ramo (Software: "Faltou funcionalidade", "Ficou com a ferramenta atual"; Imobiliário: "Financiamento negado", "Imóvel não atendeu"; Educação: "Escolheu outra instituição", "Horário incompatível"; e assim por diante, definidos no config). Assim como os estágios, só substituem os criados no cadastro, se ainda não foram usados em nenhuma perda.

### Modelos de tarefa por canal

Criados para o admin que respondeu (os modelos de tarefa são por usuário hoje), no máximo 4, na ordem dos canais escolhidos:

| Canal | Modelo (título · prazo) |
|---|---|
| WhatsApp | Mandar WhatsApp de acompanhamento · 2 dias |
| Ligação | Ligar para retomar o contato · 3 dias |
| E-mail | Enviar e-mail com a proposta · 1 dia |
| Reunião online ou presencial | Confirmar a reunião · 1 dia |
| Visita | Agendar a visita · 2 dias |
| LinkedIn | Conectar no LinkedIn · no mesmo dia |
| Instagram ou Facebook | Responder pelo direct · no mesmo dia |

"Outro" não cria modelo.

### Novo campo: Origem do lead

- Coluna `origem` (texto curto, opcional) em `usuarios` (a tabela de leads).
- Aparece no cadastro e na edição do lead como lista. As opções são as origens que a empresa marcou no questionário, mais "Outro". Sem questionário respondido, a lista usa todas as opções do config.
- Aparece como coluna na grade de Leads (pode ser ocultada no menu de colunas), entra na exportação CSV e é aceita na importação.
- Entra em `RegrasDeLead`: valor livre de até 60 caracteres, para não quebrar importações com origens fora da lista.

### Onde as respostas ficam e quem as vê

- Tabela nova `perfis_de_empresa` (uma linha por tenant), com: as respostas (JSON), `respondido_em`, `pulado_em`, `respondido_por` (user), `telefone`, `aceita_dicas_whatsapp` e `aceite_dicas_em`.
- O dono do UserFlow recebe um e-mail no `COMERCIAL_EMAIL` quando um questionário é concluído, com empresa, plano em teste e as respostas. Reaproveita o padrão de `AvisoDeAssinaturaMail` (novo tipo, por exemplo `comercial_perfil`).
- A visão do Dashboard por cargo vira uma preferência do usuário (`dashboard_visao`: `equipe` ou `meus`), só para o **Dashboard** (Relatórios, Leads e Pipeline não mudam). Ela define o filtro de vendedor quando a pessoa abre o Dashboard sem filtro na URL. Quando a pessoa escolhe outro vendedor no filtro, nada muda na preferência. Quando escolhe "Toda a equipe" ou a si mesma, a preferência passa a ser `equipe` ou `meus`, e é isso que ela verá ao voltar. Só vale para quem tem `leads.view-all`; os demais já veem só os seus.

## Parte 2: boas-vindas

- Um modal (`AppModal`) aparece na primeira página do app quando o passo a passo do usuário está **pendente**. Para quem criou a empresa, isso é logo depois do questionário; para quem entrou por convite, no primeiro acesso.
- O texto é "Bem-vindo ao UserFlow, {primeiro nome}!", seguido de "Vamos montar sua rotina de vendas juntos. Leva uns dois minutos."
- Dois botões: **Vamos lá!** (primário), que começa o passo a passo, e **Prefiro explorar sozinho**, que o marca como cancelado.
- Fechar o modal pelo X ou pelo Esc também cancela.
- O flash atual do convite ("Bem-vindo à {empresa}. Comece pelo Pipeline.") deixa de existir quando o modal for mostrado, para não duplicar.

## Parte 3: o passo a passo

### Como funciona

- Um componente de "balão guiado" próprio, sem biblioteca nova:
  - a página escurece (`fg-0/40`), com um recorte iluminado em volta do elemento alvo;
  - um balão (cartão `bg-bg-1`, raio 20, sombra 3) aponta para o alvo, com seta, título, frase, contador "2 de 7" e o **X**;
  - é posicionado por `utils/posicionar.js`, que já existe para tooltips e dropdowns.
- Os alvos são marcados no HTML com `data-tour="..."`, como `data-tour="novo-lead"` e `data-tour="nova-tarefa"`. O passo a passo nunca depende de classe CSS ou texto.
- **Passo de ação:** o balão não tem Próximo. Ele espera um evento (`useTour().avancar('lead-criado')`) que a tela emite quando a ação acontece, ou espera a navegação para a rota esperada. O clique no alvo funciona normalmente; o recorte não bloqueia o alvo.
- **Passo de mostrar:** tem o botão **Entendi**.
- **X em qualquer balão:** cancela o passo a passo inteiro, sem confirmação.
- Se o alvo não estiver na tela, o balão aparece centralizado, sem seta e com **Entendi**, em vez de travar. Isso cobre o menu lateral escondido no celular, a falta de permissão e a página que não carregou.
- O passo atual fica gravado no servidor. Navegar (Inertia) ou recarregar a página retoma do mesmo passo.
- Acessibilidade:
  - o balão é um `role="dialog"` com `aria-labelledby`, e o foco vai para ele;
  - o Esc equivale ao X;
  - o recorte não impede o uso do teclado no alvo;
  - movimento reduzido é respeitado.

### Versão completa (quem nunca usou CRM, ou pulou o questionário)

| # | Onde | Alvo | Texto | Avança quando |
|---|---|---|---|---|
| 1 | Pipeline (`/kanban`; o passo leva para lá) | botão "Novo lead" | **Cadastre seu primeiro lead.** Clique aqui para adicionar um contato ao funil. | o modal de novo lead abre |
| 2 | Modal de novo lead | campo nome | **Experimente cadastrar "Cliente teste".** Preencha o nome e um e-mail qualquer (como teste@exemplo.com) e salve. Você pode apagar depois. | o lead é salvo |
| 3 | Pipeline | cartão do lead criado | **Este é o seu lead no funil.** Quando a negociação avançar, arraste o cartão para o próximo estágio. Clique nele para abrir. | o perfil do lead abre |
| 4 | Perfil do lead | botão "Nova tarefa" | **Agende o próximo contato.** Tarefas lembram você de não deixar ninguém esperando. | a tarefa é salva |
| 5 | Qualquer tela | item "Tarefas" do menu | **Suas tarefas do dia ficam aqui.** As de hoje e as atrasadas, num lugar só. | Entendi |
| 6 | Dashboard | quadro de KPIs | **Aqui você vê o que já fatura e o que ainda vai entrar.** | Entendi |
| 7 | Qualquer tela (só se o usuário puder convidar e a empresa tiver mais de 1 pessoa: pela resposta do questionário ou, sem resposta, por usuários ativos + convites pendentes) | item "Equipe" do menu | **Convide sua equipe.** Cada vendedor vê a própria carteira; você vê todas. | Entendi |
| 8 | Centro | — | **Pronto! Seu CRM está montado.** Se quiser rever, o passo a passo fica no menu da sua conta. | Concluir |

### Versão curta (quem já usou CRM)

Só passos de mostrar, todos com Entendi:
1. Novo lead, no Pipeline.
2. Importar planilha, em Leads.
3. Tarefas.
4. Dashboard.
5. Equipe (com a mesma condição da completa).
6. Pronto.

### Estado do passo a passo

- Colunas novas em `users`:
  - `tour_estado` (`pendente`, `em_andamento`, `concluido` ou `cancelado`);
  - `tour_versao` (`completa` ou `curta`);
  - `tour_passo` (chave do passo atual).
- Uma rota `PATCH /api/tour` (só o próprio usuário) para iniciar, avançar, concluir e cancelar.
- O estado vai para o front pelo `HandleInertiaRequests`, junto do `auth.user`.
- O menu da conta, no desktop e na gaveta mobile, ganha **"Refazer o passo a passo"**. Ele volta o estado para `em_andamento`, no primeiro passo, sem o modal de boas-vindas.

## Migração

- `perfis_de_empresa`: o cadastro de uma empresa nova cria a linha **pendente**, e só uma linha pendente leva ao questionário. Empresas que já existem ficam sem linha e nunca veem o questionário.
- `users.tour_estado`: usuários que já existem ficam com `concluido`. Os novos nascem com `pendente`, no cadastro e no aceite de convite.
- `usuarios.origem`: nulo para todos.

## Política de privacidade

A página `/privacidade` ganha, na seção "Dados que coletamos", um trecho sobre o questionário de boas-vindas. Ele diz:
- quais dados são coletados (cargo, ramo, tamanho da equipe, canais e origens de venda, como conheceu, celular/WhatsApp e o aceite de dicas por WhatsApp);
- para que servem: configurar o sistema da empresa e permitir o contato comercial.

O texto é redigido por quem constrói e revisado pelo dono antes de publicar. Clientes atuais não recebem aviso, porque não respondem o questionário.

## Restrições

- Laravel 11 + Inertia 2 + Vue 3 + Tailwind 3, com os componentes `ui/` e os tokens de `theme.css`. Nada de biblioteca nova de tour.
- Multi-tenant: tudo filtrado por tenant, como o resto do sistema. O e-mail ao comercial não vaza dados entre empresas.
- Rotas de escrita autorizadas (o teste `TodaRotaDeEscritaAutorizaTest` exige isso). O questionário é só para quem tem `configuracoes.manage` na empresa que ainda não respondeu.
- Testes primeiro (PHPUnit no banco `CRMLeader_test`, Vitest no front), suíte inteira verde, `vendor/bin/pint --test` e `npm run build` limpos.
- Nada de commit sem o usuário pedir.

## Decisões minhas (o usuário aprovou o conjunto; estas são escolhas de detalhe)

- Os textos exatos das perguntas, das opções, dos estágios por ramo e dos balões estão neste documento. Podem ser ajustados no config sem mudar o desenho.
- "Pular e usar o padrão" existe, é definitivo e leva aos padrões de hoje, com o passo a passo completo.
- Convidados e "Refazer o passo a passo" usam a versão curta (só mostrar).
- O balão do novo lead pede nome e e-mail: o e-mail continua obrigatório e único no lead, como hoje.
- Os estágios e motivos só são substituídos se ainda não houver uso. Nunca apagam nada que a pessoa tenha criado.
- A visão do Dashboard por cargo é uma preferência por usuário, aplicada só sem `user_id` na URL.
- As respostas e o telefone chegam ao dono do UserFlow por e-mail e ficam gravadas. Não há tela administrativa para elas nesta etapa.
- O lead "Cliente teste" do passo a passo é um lead comum. A própria frase do balão diz que dá para apagar.

## O que fica com quem constrói

- A estrutura exata do componente do balão, do composable `useTour` e dos eventos que as telas emitem.
- Os motivos de perda específicos de cada ramo, além dos exemplos acima.
- O desenho das telas do questionário dentro do estilo atual: cartões de opção, chips e listas.
- Como cobrir o passo de "arrastar o cartão": só explicar, sem exigir arrasto.
