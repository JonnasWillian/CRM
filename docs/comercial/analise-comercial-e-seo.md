# Análise comercial e SEO — lançamento do CRM

**Data:** 2026-10-22
**Produto:** o nome atual é "UserFlow" (`APP_NAME`); ver o risco de marca na seção 10.
**Status:** o site público, os preços e os textos legais já estão no sistema (`/`, `/precos`, `/termos`, `/privacidade`). Este documento explica as decisões e lista o que ainda falta para vender.

---

## 1. Resumo executivo

O produto está pronto para ser mostrado e testado por clientes, não ainda para ser cobrado. O que ele entrega hoje cobre o que um CRM de entrada precisa ter (pipeline, tarefas, equipe, importação, relatórios) e tem um diferencial que nenhum concorrente nacional de entrada oferece bem: **contratos recorrentes contados mês a mês, separando o que já fatura do que está em projeção**. Isso define o público: empresas de serviço com mensalidade.

A proposta: entrar **abaixo do RD Station CRM** no preço de entrada (R$ 49 mensal / R$ 39 anual por usuário), cobrar o plano Profissional (R$ 89 / R$ 69) pela recorrência e pelos relatórios, e vender para agências, consultorias e estúdios de 2 a 30 vendedores. Antes de cobrar, faltam cinco coisas: cobrança, limites de plano, domínio com HTTPS, e-mail transacional real e revisão jurídica dos termos (seção 9).

## 2. O que o produto vende hoje

| Capacidade | Estado | Argumento de venda |
|---|---|---|
| Pipeline Kanban com arrastar, ganho com valor fechado, perda com motivo | Pronto | "Veja a carteira inteira numa tela" |
| Leads com valor, probabilidade e previsão; alerta de +14 dias sem contato | Pronto | Forecast ponderado sem planilha |
| Tarefas, modelos de follow-up, página de tarefas do dia | Pronto | "A planilha não avisa; o CRM avisa" |
| Equipe: convites, papéis, transferência de carteira, desativação | Pronto | Vende para equipe, não para uma pessoa |
| Importar/exportar CSV com mapeamento e erros por linha | Pronto | Adoção no primeiro dia; sem fidelidade |
| Contratos recorrentes: MRR, renovações, aditivos, faturado × a realizar | Pronto | **Diferencial** |
| Relatórios: visão geral, por vendedor, por que perdemos | Pronto | Gestor compara vendedores |
| Lixeira, histórico completo, tema escuro, português, reais | Pronto | Polimento percebido |
| WhatsApp | Só botão `wa.me` | Concorrentes nacionais têm mais (seção 4) |
| Campos personalizados, tags, contatos × empresas, API, app | Não existe | Lacunas conhecidas (`docs/analise/2026-09-27-lacunas-de-mercado.md`) |

## 3. Público-alvo e posicionamento

**Cliente ideal (ICP):** empresa brasileira de serviços B2B que vende por proposta e cobra mensalidade: agências de marketing e tráfego, consultorias, estúdios de design/desenvolvimento, escritórios de contabilidade com venda ativa, assessorias. Equipe comercial de 2 a 30 pessoas, dono ou gestor comercial decide, hoje usa planilha, Trello ou um CRM gringo subutilizado.

**Dores que o site ataca:** follow-up esquecido; CRM em dólar com recursos demais; receita recorrente contada errado (contrato renovado três vezes vira "um projeto").

**Mensagem central:** "O CRM de vendas que mostra o que você já fatura e o que ainda vai entrar." Três pilares: simples e em português; em reais, sem surpresa; conta a recorrência certo.

**O que NÃO prometer ainda:** integração oficial de WhatsApp, app nativo, automações. O site diz "no roteiro" para o WhatsApp e não cita o resto.

## 4. Concorrência

| Concorrente | Preço de entrada (por usuário/mês) | Onde ganha de nós | Onde perdemos dele |
|---|---|---|---|
| RD Station CRM | Grátis até 4 usuários; Basic R$ 73 (R$ 65,70 anual); Pro R$ 131 (R$ 117,90 anual), Pro com mínimo de 4 usuários; implantação R$ 849 ([página oficial](https://www.rdstation.com/planos/crm/), consultada em 22/10/2026) | Marca, WhatsApp, integração com RD Marketing, plano grátis | Preço de entrada, simplicidade, recorrência |
| Agendor | Grátis até 3 usuários; Pro R$ 59; Performance R$ 83; Corporativo R$ 156; WhatsApp Sync R$ 49/número (levantamento de 27/09/2026) | WhatsApp, app mobile, maturidade | Recorrência/MRR, relatório de perdas, preço anual |
| Pipedrive | US$ 14 a US$ 79 por usuário, cobrado em dólar; a partir de ~R$ 80 e Growth em torno de R$ 197 com câmbio ([Pipecon](https://www.pipecon.com.br/blog/artigo/pipedrive-preco-planos-2026), [Stackhorse](https://stackhorse.com/pipedrive-preco-brasil/)) | Automações, integrações, API | Preço em dólar, complexidade, suporte em inglês |
| Kommo | Plano em dólar, caixa unificada de WhatsApp/Instagram | WhatsApp é o produto | Vendas B2B por proposta, relatórios financeiros |
| Ollow (ex-Moskit) | R$ 995 a R$ 2.074 por conta (levantamento de 27/09/2026) | Operação de WhatsApp em volume | Preço para equipes pequenas |
| HubSpot Sales | Grátis limitado; pagos em dólar | Marca, marketing integrado | Preço e complexidade |

**Leitura:** o mercado de entrada no Brasil está entre R$ 59 e R$ 73 por usuário. O cliente espera: equipe, vários funis, importação, tarefas, dashboard e algum WhatsApp. Entregamos tudo menos o WhatsApp profundo; a recorrência é o argumento que nenhum deles tem na mesma faixa.

Os preços dos concorrentes mudam; antes de publicar comparativos, confirme na página oficial de cada um.

## 5. Preços propostos (publicados em `/precos`, editáveis em `config/comercial.php`)

| Plano | Mensal | Anual (por mês) | Limites | O que justifica |
|---|---|---|---|---|
| Inicial | R$ 49 | R$ 39 | até 3 usuários, 1 funil, 2.000 leads | Entrada abaixo do RD Basic e do Agendor Pro |
| Profissional | R$ 89 | R$ 69 | ilimitado | Equipe, recorrência/MRR, relatórios, vários funis |
| Empresa | R$ 139 | R$ 109 | a partir de 5 usuários | Serviços: importação assistida, onboarding, suporte prioritário, DPA |

Regras: por usuário **ativo** (desativado não conta — o sistema já tem o conceito), 14 dias grátis com todos os recursos e sem cartão, anual com cerca de 20% de desconto. Sem plano grátis permanente: o grátis do RD e do Agendor custa suporte e atrai quem não paga; para uma operação pequena, o teste de 14 dias converte melhor e é mais simples de operar.

**Conta de padaria:** 10 clientes com 4 usuários no Profissional anual = R$ 2.760/mês. 50 clientes nesse perfil = R$ 13.800/mês. Com ticket de ~R$ 280/cliente/mês, um CAC de até R$ 600 por cliente pago se paga em pouco mais de 2 meses; acima de R$ 1.000 começa a apertar, salvo churn abaixo de 3% ao mês.

**Estado atual:** teste de 14 dias, limites por plano, faturas por usuário, carência, suspensão e e-mails de cobrança já estão no app. Falta o gateway de pagamento: ver o [plano de cobrança](plano-de-cobranca-e-assinaturas.md).

## 6. Funil e metas

| Etapa | Definição | Meta inicial |
|---|---|---|
| Visita → cadastro | Criou conta | 3 a 5% das visitas |
| Cadastro → ativação | Importou ou criou ≥ 5 leads **e** criou ≥ 1 tarefa em 7 dias | 40% |
| Ativação → pago | Escolheu plano até o fim do teste | 15 a 25% |
| Retenção | Churn mensal | < 4% nos 6 primeiros meses |

Para medir, faltam: eventos no GA4 (clique em "Começar grátis", cadastro concluído), um relatório interno de contas por etapa (cadastro, ativação, dias de teste restantes) e um e-mail no 3º, 7º e 12º dia de teste.

## 7. SEO

### Feito nesta entrega
- Site público em HTML pronto no servidor (Blade), sem depender de JavaScript para o conteúdo: Google e redes sociais leem tudo na primeira requisição.
- `<title>` e `<meta description>` por página; `lang="pt-BR"`; canônica; Open Graph e Twitter Card com `og.png` (1200×630, gerado de `/og-card`).
- Dados estruturados: Organization, SoftwareApplication com os três planos como Offer em BRL, e FAQPage (as perguntas da página podem aparecer como "rich result").
- `sitemap.xml` e `robots.txt` dinâmicos; telas do app com `noindex`; fontes hospedadas localmente; favicon SVG.
- Página inicial sem scroll horizontal a 375 px e sem imagens externas (carrega rápido).

### Pendências técnicas
1. Definir o domínio e colocar `APP_URL` com HTTPS em produção: canônica, sitemap e `og.png` usam esse valor.
2. Cadastrar no Google Search Console e enviar o sitemap; medir Core Web Vitals no PageSpeed depois de publicar.
3. Preencher `COMERCIAL_GA4_ID` (ou trocar por Plausible/Umami se quiser evitar aviso de cookies).
4. Trocar os mocks em HTML por capturas reais do produto com `alt` descritivo quando houver dados de demonstração sem nomes de teste.

### Palavras-chave (validar volume no Google Keyword Planner antes de investir)
| Intenção | Termos | Página |
|---|---|---|
| Genérica | crm de vendas, crm para pequenas empresas, crm simples, crm em português | `/` |
| Nicho (diferencial) | crm para agências, crm para consultoria, crm com controle de contratos recorrentes, controle de mrr para agência | `/` seção Receita recorrente; posts |
| Comparação | alternativa ao pipedrive em reais, rd station crm ou agendor, crm barato por usuário | posts de comparação |
| Preço | crm preço por usuário, crm 14 dias grátis sem cartão | `/precos` |

### Conteúdo (um post por semana, 800 a 1.500 palavras, com CTA para o teste)
1. Como calcular o MRR de uma agência (e por que a planilha erra)
2. Pipeline de vendas para prestadores de serviço: estágios que funcionam
3. Alternativas ao Pipedrive em reais (comparativo honesto)
4. RD Station CRM × Agendor × [nós]: para quem cada um serve
5. Como importar sua planilha de leads sem duplicar contatos
6. Follow-up: o roteiro de 5 toques que fecha proposta parada
7. Previsão de receita ponderada: o número que o gestor deveria olhar toda segunda
8. Renovação de contrato: como registrar reajuste e aditivo sem perder o histórico
9. LGPD para equipes de vendas: o que pode e o que não pode fazer com a base
10. Taxa de ganho por vendedor: como ler o relatório e o que fazer com ele

### Autoridade
Cadastro no B2B Stack, Capterra Brasil e GetApp; página no LinkedIn; pedido de avaliação aos primeiros 10 clientes.

## 8. Canais de aquisição (ordem de prioridade)

1. **Indicação com incentivo:** um mês grátis para quem indica e para quem entra. Custo zero, e o ICP (agências) se conhece.
2. **Conteúdo + SEO:** os posts acima; retorno em 3 a 6 meses.
3. **Google Ads em termos de comparação:** "alternativa ao pipedrive", "crm em reais" — tráfego pequeno e barato, com intenção alta.
4. **Parcerias:** contadores, consultorias de gestão e comunidades de agências; comissão de 20% no primeiro ano.
5. **Venda direta do fundador:** 20 demonstrações de 20 minutos por mês para agências da sua rede; o feedback vale mais que o fechamento nos primeiros 90 dias.

## 9. O que falta para cobrar (bloqueantes)

| # | Item | Por quê |
|---|---|---|
| B1 | Cobrança: a Fase 1 do [plano de cobrança](plano-de-cobranca-e-assinaturas.md) está feita (faturas por usuário, só o pagamento ativa, rotina e e-mails). Falta o gateway (Stripe, Asaas ou Pagar.me) com nota fiscal; até lá, confirme cada pagamento com `php artisan fatura:pagar {fatura}` | Sem isso não há receita automática |
| B2 | ~~Limites de plano e fim do teste~~ **Feito em 22/10/2026:** toda empresa nasce em teste de 14 dias; teste expirado, plano vencido ou cancelado bloqueia escrita (402) e mantém leitura; limites do plano (usuários ativos, funis, leads) valem fora do teste; página Configurações › Assinatura escolhe o plano; `assinatura:ativar` renova à mão. Falta só ligar o gateway em `Assinaturas::ativar()` | — |
| B3 | Domínio, HTTPS e `APP_URL` | SEO, OG e e-mails apontam para o domínio |
| B4 | E-mail transacional real (SMTP ou API): convites, redefinição de senha, avisos de teste | Hoje `MAIL_MAILER=log` |
| B5 | Revisão jurídica dos Termos e da Política de privacidade; CNPJ e razão social em `COMERCIAL_*` | Os textos são um rascunho razoável, não um parecer |
| B6 | Backup diário do banco e dos anexos, com restauração testada | Dados do cliente |
| B7 | Monitoramento e página de status (ou ao menos alerta de erro) | Confiança e suporte |
| B8 | Canal de suporte definido (e-mail dedicado) e `COMERCIAL_EMAIL` preenchido | O site mostra `hello@example.com` enquanto vazio |
| B9 | Onboarding na primeira entrada: checklist "importe, convide, crie uma tarefa" | Ativação é o passo que mais converte em pago |
| B10 | Decidir o nome da marca (seção 10) antes de divulgar o domínio | Trocar depois custa SEO |

Desejáveis logo depois: integração oficial de WhatsApp, campos personalizados, API/webhooks, app ou PWA, metas por vendedor.

## 10. Risco de marca: "UserFlow"

Existe um produto SaaS chamado **Userflow** (userflow.com), de onboarding in-app, fundado em 2018 e adquirido pela Beamer em 2024 ([site oficial](https://userflow.com/faqs), [G2](https://www.g2.com/products/userflow/discuss)). É um nome registrado, com presença forte nos buscadores em inglês. Lançar um CRM com o mesmo nome traz dois problemas: disputa de marca e disputa de SEO que não vamos ganhar.

**Recomendação:** escolher outro nome antes de comprar domínio e começar o conteúdo. "CRMLeader" (nome do banco e dos documentos antigos) é uma opção; verifique disponibilidade de domínio `.com.br` e no INPI. A troca no sistema é uma linha: `APP_NAME` no `.env` alimenta o logo, os títulos, o site, os termos e os e-mails. Depois, regenerar `public/og.png` (captura de `/og-card` em 1200×630) e o `favicon.svg`.

## 11. Plano de 90 dias

| Semanas | Entrega |
|---|---|
| 1–2 | Nome e domínio; HTTPS; e-mail transacional; GA4 e Search Console; revisão jurídica iniciada |
| 3–4 | Cobrança e limites de plano (B1, B2); e-mails de teste (dia 3, 7, 12); onboarding na primeira entrada |
| 5–6 | 10 demonstrações com agências da rede; 5 clientes-piloto com desconto vitalício de 30% em troca de feedback e depoimento |
| 7–8 | Posts 1 a 4; cadastro nos diretórios; capturas reais no site |
| 9–12 | Google Ads em termos de comparação (R$ 30/dia para começar); WhatsApp oficial em descoberta; revisão de preços com os dados dos pilotos |

## Fontes consultadas em 22/10/2026
- [RD Station CRM: planos e preços](https://www.rdstation.com/planos/crm/)
- [RD Station CRM preço 2026 (melhorescrm.com)](https://melhorescrm.com/noticias/rd-station-crm-precos-planos-2026/)
- [Pipedrive preço 2026 (Pipecon)](https://www.pipecon.com.br/blog/artigo/pipedrive-preco-planos-2026)
- [Pipedrive preço Brasil 2026 (Stackhorse)](https://stackhorse.com/pipedrive-preco-brasil/)
- [Preços do Pipedrive em 2026 (PromptLab)](https://trypromptlab.com/pt-br/blog/pipedrive-pricing)
- [Userflow — FAQs](https://userflow.com/faqs) · [Userflow no G2](https://www.g2.com/products/userflow/discuss) · [Userflow (Craft)](https://craft.co/userflow)
- Levantamento interno de 27/09/2026: `docs/analise/2026-09-27-lacunas-de-mercado.md` (Agendor, Ollow, Kommo)
