# Lacunas em relação ao padrão de mercado

**Data:** 2026-09-27
**Origem:** análise crítica do CRMLeader comparado a RD Station CRM, Pipedrive, HubSpot Sales Hub, Agendor, Ploomes, Kommo, Zoho CRM, Ollow (antigo Moskit) e Salesforce
**Status:** levantamento. Este documento é a base do plano de lançamento, que vem depois das correções de [problemas críticos](2026-09-27-problemas-criticos.md).

Os itens abaixo são recursos que **todos ou quase todos** os CRMs pesquisados oferecem já nos planos de entrada. Os IDs (L1…L17) são estáveis e estão ordenados da maior para a menor prioridade.

## Resumo

| # | Lacuna | Prioridade para lançamento |
|---|---|---|
| L1 | Equipe: convites e gestão de papéis | Bloqueante |
| L2 | Transferir leads entre vendedores | Bloqueante |
| L3 | Contatos e empresas separados de negócios | Alta |
| L4 | Negócio com valor, probabilidade e data prevista | Alta |
| L5 | Campos personalizados e tags | Alta |
| L6 | Importar e exportar CSV/Excel | Bloqueante |
| L7 | WhatsApp | Bloqueante (no Brasil) |
| L8 | E-mail pelo CRM e modelos | Média |
| L9 | Agenda: tarefas com hora, responsável e tipo; sincronização de calendário | Alta |
| L10 | Notificações e lembretes | Alta |
| L11 | Metas por vendedor e ranking | Média |
| L12 | Dashboard com seleção de período | Alta |
| L13 | Captura de leads (formulário e webhook) | Média |
| L14 | API pública e webhooks de saída | Média |
| L15 | App mobile ou PWA | Média |
| L16 | Detecção de duplicados e merge | Baixa |
| L17 | LGPD: direitos do titular e base legal | Alta (obrigação legal) |

---

## L1. Equipe: convidar usuários e gerenciar papéis

- **Hoje:** cada cadastro cria uma empresa (tenant) nova, e o e-mail do usuário é único no sistema todo. Não existe convite, tela de usuários nem tela para atribuir papéis. Os papéis `admin`, `gestor` e `vendedor` existem (spatie/permission), mas só são atribuídos no código. A permissão `agentes.manage` foi criada e nunca usada.
- **Mercado:** todos têm convite por e-mail, lista de usuários, ativar e desativar, e escolha de perfil. RD Pro/Advanced, Pipedrive Premium+ e Agendor Corporativo têm perfis de permissão granulares.
- **Por que importa:** um CRM é vendido para equipes. Sem isso, o RBAC construído na spec `2026-09-15-autorizacao-rbac-design.md` não tem uso real, e o produto só funciona para uma pessoa por empresa.

## L2. Transferir leads entre vendedores

- **Hoje:** o dono do lead (`usuarios.user_id`) é definido na criação e não muda pela interface.
- **Mercado:** transferência individual e em massa é padrão; no Pipedrive e no Zoho há também regras de atribuição.
- **Por que importa:** é uma operação rotineira do gestor (férias, desligamento, redistribuição de carteira). Também é pré-requisito para corrigir [P3](2026-09-27-problemas-criticos.md#p3-excluir-o-perfil-apaga-todos-os-leads-do-vendedor).

## L3. Contatos e empresas separados de negócios

- **Hoje:** o lead (`Usuario`) é um único registro de pessoa (nome, e-mail, telefone, descrição). `Projeto` funciona como negócio, pendurado no lead.
- **Mercado:** o modelo padrão é **Pessoa ↔ Empresa ↔ Negócio**. Uma empresa tem vários contatos e vários negócios, e um contato pode participar de vários negócios.
- **Por que importa:** na venda B2B o comprador é a empresa, com várias pessoas envolvidas. Sem essa separação, há duplicação de dados e não existe visão de conta nem histórico por cliente.

## L4. Negócio com valor, probabilidade e data prevista de fechamento

- **Hoje:** só `Projeto.preco`, com datas de início e fim do projeto e parcelas. O lead em si não tem valor. O kanban mostra a soma dos projetos abertos.
- **Mercado:** todo negócio tem valor, etapa, probabilidade (padrão da etapa ou manual), data prevista de fechamento e, muitas vezes, produtos vinculados. O funil mostra o valor ponderado.
- **Por que importa:** sem isso não existe previsão de receita (forecast), que é o principal número que o gestor comercial acompanha.

## L5. Campos personalizados e tags

- **Hoje:** não existem. A antiga tabela `tags` foi renomeada para `estagios`, então não há etiquetas livres.
- **Mercado:** campos personalizados por entidade (texto, número, data, lista, moeda) e tags para segmentação. No RD, só a partir do plano pago.
- **Por que importa:** cada segmento de cliente (imobiliária, SaaS, indústria) precisa registrar informações próprias. Sem isso, a informação vai parar em "descrição" e não pode ser filtrada nem usada em relatório.

## L6. Importar e exportar CSV/Excel

- **Hoje:** não existe.
- **Mercado:** padrão em todos, com mapeamento de colunas, prévia e tratamento de duplicados.
- **Por que importa:** a primeira ação de um cliente novo é trazer a base da planilha ou do CRM anterior. Sem importação, a adoção trava no primeiro dia. A exportação também é uma exigência de confiança (o cliente não fica preso) e da LGPD (portabilidade).

## L7. WhatsApp

- **Hoje:** não existe. O telefone é inteiro e não aceita o formato internacional ([P5](2026-09-27-problemas-criticos.md#p5-telefone-salvo-como-número-inteiro)).
- **Mercado:**
  - **Kommo:** API oficial nativa, caixa de entrada unificada com Instagram e Messenger, e bots. É a referência.
  - **Ollow/Moskit:** API oficial mais extensão para o WhatsApp Web.
  - **RD Station CRM:** "vendas por WhatsApp" em todos os planos, inclusive o gratuito (segundo reviews, via WhatsApp Web).
  - **Agendor:** extensão gratuita e WhatsApp Sync a R$ 49 por número por mês.
- **Por que importa:** no Brasil, é o critério número um de escolha de CRM. A maior parte da conversa comercial acontece no WhatsApp.
- **Caminho incremental sugerido:**
  1. Botão `wa.me` no lead, com registro manual da conversa na timeline.
  2. Modelos de mensagem.
  3. Integração pela API oficial (Cloud API) com caixa de entrada.

## L8. E-mail pelo CRM e modelos de mensagem

- **Hoje:** o envio está configurado só para log (`MAIL_MAILER=log`). Não há Mailables, Notifications, modelos nem integração IMAP.
- **Mercado:** envio pelo CRM com modelos é padrão; sincronização da caixa e rastreio de abertura aparecem nos planos intermediários (Pipedrive Growth, HubSpot Starter).
- **Por que importa:** registrar a comunicação no histórico do lead e padronizar a abordagem da equipe.

## L9. Agenda: tarefas com hora, responsável e tipo; sincronização de calendário

- **Hoje:** `Tarefa` tem só `data_limite` (data, sem hora), está sempre ligada a um lead e não tem responsável (assume o dono do lead) nem tipo (ligação, reunião, visita, e-mail). Não há visão de calendário.
- **Mercado:** atividades com tipo, data e hora, duração e responsável; visão de agenda; sincronização com Google Agenda e Outlook (no RD, a partir do Pro).
- **Por que importa:** a rotina do vendedor é organizada por agenda. Sem hora, não há lembrete útil nem marcação de reunião.

## L10. Notificações e lembretes

- **Hoje:** nenhuma notificação (no app, por e-mail ou push). A fila (`database`) e o agendador estão configurados, mas sem nenhum job nem tarefa agendada. Só existem os avisos SweetAlert na tela.
- **Mercado:** lembrete de tarefa, aviso de lead atribuído, menções, resumo diário.
- **Por que importa:** sem lembrete, as tarefas são esquecidas e o CRM vira só um lugar para registrar. Esse também é o alicerce técnico de L8, L11 e das automações.

## L11. Metas por vendedor e ranking

- **Hoje:** não existe.
- **Mercado:** metas mensais de valor e quantidade por vendedor e equipe, com ranking (RD Basic+, Agendor Performance, Moskit).
- **Por que importa:** é o que o gestor mais olha no dia a dia e o que motiva a equipe.

## L12. Dashboard com seleção de período

- **Hoje:** existem os cards de indicadores do Dashboard (`/api/metricas`, com períodos fixos) e o relatório de perdas. A spec [2026-09-21-camada-de-relatorios-design.md](../superpowers/specs/2026-09-21-camada-de-relatorios-design.md) está pronta e não foi implementada: `metricas()` continua em `Userarios.php` e não existe `app/Services/Reports`.
- **Mercado:** dashboard com filtro de período, vendedor e funil; funil de conversão; tempo médio por etapa; ganhos e perdas.
- **Por que importa:** o gestor precisa comparar períodos e vendedores para tomar decisão.

## L13. Captura de leads (formulário e webhook de entrada)

- **Hoje:** o lead só entra digitado à mão.
- **Mercado:** formulário embutível no site, webhook de entrada, integração com RD Station Marketing e Facebook/Instagram Lead Ads (nativa no Kommo, HubSpot e Zoho; via Zapier nos demais).
- **Por que importa:** o lead precisa chegar sozinho e rápido. A velocidade de resposta influencia diretamente a conversão.

## L14. API pública e webhooks de saída

- **Hoje:** o Sanctum é usado só para a sessão da SPA. `User` não tem `HasApiTokens`, então a tabela `personal_access_tokens` não serve para nada. Não há webhooks de saída.
- **Mercado:** API REST documentada, webhooks, e integração com Zapier, Make e Pluga (no RD, a API só existe a partir do Pro).
- **Por que importa:** permite que o cliente integre com o que já usa (ERP, site, planilhas) sem depender de você. É também a base para L13.

## L15. App mobile ou PWA

- **Hoje:** só o layout responsivo, com menu mobile.
- **Mercado:** apps iOS e Android em todos os CRMs pesquisados; o Agendor destaca o foco em venda externa.
- **Por que importa:** vendedor externo registra visita e ligação pelo celular. Um PWA (instalável, com ícone e acesso rápido) resolve boa parte a um custo baixo.

## L16. Detecção de duplicados e merge

- **Hoje:** só a regra de e-mail único por empresa na criação.
- **Mercado:** aviso de possível duplicado (e-mail, telefone, nome) e mesclagem de registros (forte em HubSpot, Zoho e Salesforce).
- **Por que importa:** passa a ser urgente depois de L6 (importação) e L13 (captura automática), que geram duplicados.

## L17. LGPD: direitos do titular e base legal

- **Hoje:** nada específico. Não há exportação dos dados de um titular, anonimização, registro de base legal nem política de retenção aplicada.
- **Mercado:** DPA (acordo de tratamento de dados) e atendimento aos pedidos do titular são padrão. O RD Station CRM registra a base legal por contato, inclusive na API.
- **Por que importa:** como operador de dados pessoais dos clientes dos seus clientes, o CRM precisa permitir acesso, correção, portabilidade e eliminação. Depende também de [P1](2026-09-27-problemas-criticos.md#p1-anexos-em-disco-público-sem-separação-por-empresa-e-sem-autenticação) e [P4](2026-09-27-problemas-criticos.md#p4-excluir-um-lead-é-definitivo-em-cascata-e-deixa-dados-órfãos).

---

## Apêndice A: diferenciais para depois do lançamento

| Diferencial | Referência de mercado | Base existente no CRMLeader |
|---|---|---|
| Aviso de negócio parado (prazo por etapa) | Pipedrive | Etapas e `estagio_historicos` já existem |
| Rodízio de leads e automações por gatilho | Pipedrive Premium+, HubSpot, Zoho, Ploomes Workflow | `TarefaPadrao` (modelos de tarefa) pode virar a ação da automação |
| Cadências e sequências de contato | HubSpot Starter+, Pipedrive Growth+ | Depende de L8 e L10 |
| Produtos, propostas em PDF e assinatura eletrônica | Ploomes (CPQ, Clicksign/D4Sign), RD Basic+, Agendor | `Projeto` com preço e parcelas |
| Forecast pelo histórico de conversão | HubSpot, Pipedrive Growth+, Agendor Performance | `estagio_historicos` e `perdas` |
| IA: resumo do lead, próxima tarefa, rascunho de mensagem | RD, Agendor (Ava), Pipedrive, HubSpot Breeze | Activity log como contexto |
| Integração com ERPs brasileiros | Agendor (TOTVS, Sankhya, Omie), Ploomes (Omie), Moskit (Conta Azul) | Depende de L14 |
| Perfis de permissão granulares e auditoria completa | RD Advanced, Pipedrive Premium+, enterprise em geral | spatie/permission e activitylog já instalados |

## Apêndice B: preços de referência (por usuário por mês, set/2026)

| CRM | Preço | Observação |
|---|---|---|
| RD Station CRM | Grátis (até 4 usuários); Basic R$ 65,70 no anual / R$ 73; Pro R$ 117,90 / R$ 131 | Pro exige no mínimo 4 usuários |
| Agendor | Grátis (3 usuários); Pro R$ 59; Performance R$ 83; Corporativo R$ 156 | -10% no anual |
| Ploomes | Lite ~US$ 22 | Módulos à parte |
| Ollow (Moskit) | R$ 995 a R$ 2.074 por conta | Cobra por volume de conversas |
| Kommo | US$ 25 / 35 / 45 | Mínimo de 6 meses |
| Pipedrive | US$ 14 / 39 / 59 / 79 | Growth ≈ R$ 197 a R$ 222 com câmbio e IOF |
| HubSpot Sales Hub | Starter US$ 7 a 20; Pro US$ 90 + onboarding | |
| Zoho CRM | ~US$ 14 a 52 | IA (Zia) só a partir do Enterprise |

**Leitura:** o preço de entrada no Brasil fica entre R$ 59 e R$ 73 por usuário por mês (Agendor Pro, RD Basic). Nessa faixa, o cliente espera receber: equipe, vários funis, campos personalizados, importação, tarefas com agenda, dashboard com metas e algum tipo de WhatsApp.

## Fontes

- [RD Station CRM: planos](https://www.rdstation.com/planos/crm/) · [RD: bases legais na API](https://developers.rdstation.com/reference/crm-v1-bases-legais) · [RD e LGPD](https://ajuda.rdstation.com/s/article/Lei-Geral-de-Prote%C3%A7%C3%A3o-de-Dados-LGPD-e-RD-Station-CRM?language=pt_BR) · [RD (melhorescrm)](https://melhorescrm.com/analise/rd-station-crm/)
- [Agendor: planos e preços](https://www.agendor.com.br/planos-precos) · [Agendor 2026 (melhorescrm)](https://melhorescrm.com/noticias/agendor-precos-planos-2026/)
- [Ploomes: pricing](https://www.ploomes.com/en/pricing) · [Ploomes: assinatura eletrônica](https://blog.ploomes.com/integracao-crm-e-assinatura-eletronica/)
- [Kommo: tarifas](https://www.kommo.com/buy/tariff/)
- [Pipedrive: recursos por plano](https://support.pipedrive.com/en/article/what-features-do-the-pipedrive-plans-have) · [Pipedrive em BRL (Stackhorse)](https://stackhorse.com/pipedrive-preco-brasil/) · [Pipedrive review (Breakcold)](https://www.breakcold.com/blog/pipedrive-review)
- [HubSpot: preços Sales Hub](https://br.hubspot.com/pricing/sales)
- [Zoho CRM: preços](https://www.zoho.com/pt-br/crm/zohocrm-pricing.html) · [Zoho (Zeeg)](https://zeeg.me/en/blog/post/zoho-crm-pricing)
- [Salesforce: preços (tech.co)](https://tech.co/crm-software/salesforce-pricing-how-much-does-salesforce-cost)
- [Moskit vira Ollow (Exame)](https://exame.com/negocios/com-ia-para-vendas-no-whatsapp-moskit-vira-ollow-e-mira-r-100-milhoes/) · [Ollow: planos](https://www.ollow.com.br/planos)
