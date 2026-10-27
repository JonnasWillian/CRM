# Plano de cobrança e assinaturas

Data: 24/10/2026. Atualizado em 26/10/2026 com os itens faltantes, o enquadramento fiscal e as opções de cobrança recorrente. Complementa a [análise comercial](analise-comercial-e-seo.md), bloqueador B1 (cobrança).

**Situação:** a Fase 1 está implementada e testada. Falta a Fase 2, que depende do gateway de pagamento. Antes dela, falta resolver o enquadramento da empresa: a atividade de software não é permitida ao MEI.

## O que falta

Os códigos B3 a B10 são os da seção 9 da [análise comercial](analise-comercial-e-seo.md).

### Antes de cobrar o primeiro cliente

- [ ] **CNPJ que pode vender software.** Desenvolvimento e licenciamento de programas não estão entre as ocupações permitidas ao MEI. Ver [Enquadramento fiscal](#enquadramento-fiscal-mei-não-pode-vender-saas).
- [ ] **Contratar o gateway** e fazer a [Fase 2](#fase-2-gateway-de-pagamento). A recomendação é o Asaas, com Pix Automático.
- [ ] **Nota fiscal de serviço** para cada pagamento, emitida pelo gateway ou pelo emissor do município.
- [ ] **Nome da marca** antes de comprar o domínio (B10).
- [ ] **Domínio, HTTPS e `APP_URL`** (B3). Os links dos e-mails de cobrança usam o `APP_URL`.
- [ ] **E-mail transacional real** (B4). Hoje `MAIL_MAILER=log`: nenhum aviso de cobrança chega ao cliente.
- [ ] **Agendador e fila no servidor:** `schedule:run` a cada minuto e `queue:work` no ar. Sem eles, a rotina diária não roda e os e-mails não saem.
- [ ] **Dados comerciais no `.env`:** `COMERCIAL_EMAIL`, `COMERCIAL_WHATSAPP`, `COMERCIAL_PAGAMENTO_INSTRUCOES`, `COMERCIAL_RAZAO_SOCIAL` e `COMERCIAL_CNPJ` (B5, B8). Sem eles, o site e as faturas mostram `hello@example.com`.
- [ ] **Revisão jurídica dos Termos e da Política de privacidade** (B5). Os Termos precisam descrever as regras deste plano: assentos, troca, carência, suspensão e cancelamento.

### Logo depois

- [ ] Backup diário do banco e dos anexos, com restauração testada (B6).
- [ ] Monitoramento e alerta de erro (B7), inclusive para falha da `assinaturas:rotina`.
- [ ] Onboarding na primeira entrada (B9).
- [ ] As seis [pendências conhecidas](#pendências-conhecidas) da revisão.

## Por que este plano existe

A auditoria de 24/10/2026 mostrou que os planos funcionavam como vitrine, não como cobrança. Oito cenários foram reproduzidos em teste automatizado, todos com falha:

| Cenário | Esperado | O que acontecia |
|---|---|---|
| Teste vencido, 5 cliques em "Ativar plano" Empresa anual | Pedir pagamento | Plano ativo por 5 anos, sem pagar |
| Plano Inicial com 2 ativos, 4 convites aceitos | Parar em 3 usuários | 6 usuários ativos |
| Limite de 2 leads, restaurar 2 leads da lixeira | Recusar | 4 leads |
| Limite de 1 funil, restaurar um funil arquivado | Recusar | 2 funis |
| 7 usuários no teste grátis, depois ativar o Inicial | Pedir para reduzir | Os 7 continuam gravando |
| Teste vencido há 2 anos | Acesso encerrado | Lê, abre o dashboard e exporta |
| Convite aceito com a assinatura bloqueada | Recusar | Conta criada |
| Rotina de cobrança e e-mails de renovação | Existir | Nenhuma tarefa agendada, nenhum e-mail |

Além disso, o preço é por usuário, mas nada calculava o valor devido.

## Decisões

- **Contratar gera uma fatura, não ativa o plano.** Só o pagamento confirmado ativa ou renova. Sem gateway, quem confirma é a equipe comercial, por comando. Com gateway, será o aviso de pagamento do próprio gateway.
- **O valor é por usuário ativo.** Fatura mensal: preço mensal × usuários ativos. Fatura anual: preço anual × 12 × usuários ativos. Mínimo de 1 usuário, ou o mínimo do plano: o Empresa cobra pelo menos 5, como anuncia.
- **Usuários são assentos comprados.** A fatura cobra N usuários e o plano pago passa a cobrir N, mesmo nos planos sem limite de usuários. Para ter mais no meio do ciclo, compra-se os assentos: é uma troca, com crédito do que sobra. Desativar gente antes da renovação reduz a fatura, mas reativar depois exige comprar o assento de novo.
- **A renovação cobra os usuários em uso** no dia em que é gerada: ativos mais convites pendentes, respeitando o mínimo do plano.
- **Enquanto houver fatura pendente, a empresa não cresce além do que ela cobre.** No teste, gerar uma fatura do Inicial para 2 usuários já limita a empresa a 2 usuários e 1 funil até ela gerar outra.
- **Renovação paga antes do fim ou durante a carência continua do vencimento anterior.** Paga depois que o acesso foi limitado, conta a partir do pagamento.
- **Pagar durante o teste grátis não encurta o teste.** O ciclo pago começa quando o teste termina.
- **Upgrade vale no pagamento, com crédito.** Upgrade é ficar mais caro por mês, com ciclo igual ou mais longo: plano maior, mais usuários ou mensal para anual. O novo ciclo começa no pagamento e a fatura desconta o valor proporcional do que sobra do ciclo atual.
- **O resto espera a janela de renovação.** Plano menor, menos usuários ou ciclo mais curto só podem ser escolhidos a partir de 7 dias antes do vencimento, e substituem a fatura de renovação.
- **Fatura de upgrade não paga vence em 3 dias** e, vencida, é cancelada para a renovação do plano atual sair.
- **Carência de 3 dias** depois do vencimento de um plano pago: a equipe continua gravando, com aviso. O teste grátis não tem carência.
- **Só leitura** depois da carência, no fim do teste ou após o cancelamento.
- **Suspensão total 30 dias depois** do início do só leitura. Ficam liberados apenas a tela de planos e faturas, a exportação de leads, o perfil e sair. A exportação continua liberada para a empresa sempre poder levar os próprios dados.
- **O teste grátis continua sem limites**, mas um plano só pode ser contratado se o uso da empresa couber nele.
- **Convites pendentes ocupam vaga** no limite de usuários. Aceitar um convite confere o limite e a situação da assinatura de novo.
- **Cancelar é cancelar a renovação.** A empresa usa até o fim do ciclo pago, não recebe mais faturas e depois fica só leitura. Pode desfazer antes do fim.

Prazos ficam em `config/comercial.php` e podem ser ajustados sem mexer no código.

## Situações da assinatura

| Situação | Quando | Grava? | Lê? |
|---|---|---|---|
| Teste | Até o fim do teste grátis | Sim | Sim |
| Ativa | Plano pago dentro do ciclo | Sim | Sim |
| Carência | Até 3 dias depois do vencimento de plano pago | Sim, com aviso | Sim |
| Teste encerrado | Fim do teste sem plano pago | Não | Sim |
| Vencida | Depois da carência | Não | Sim |
| Cancelada | Fim do ciclo com renovação cancelada | Não | Sim |
| Suspensa | 30 dias depois de qualquer situação sem gravação | Não | Só planos, faturas e exportação |

## Fase 1: sem gateway

Tudo o que não depende de escolher o gateway.

### 1. Fechar os furos de limite

- [x] Convites pendentes contam no limite de usuários, ao convidar, reenviar convite expirado e reativar membro.
- [x] Aceitar convite confere o limite e recusa quando a assinatura não permite gravar.
- [x] Restaurar lead, sozinho ou em lote pela Lixeira, confere o limite de leads.
- [x] Restaurar funil arquivado confere o limite de funis.
- [x] Contratar um plano exige que usuários, funis e leads caibam nele. A tela mostra o motivo quando não cabem.

### 2. Faturas por usuário

- [x] Tabela `faturas`: empresa, plano, ciclo, usuários cobrados, preço por usuário, meses, valor, situação, vencimento, pagamento e referência do gateway.
- [x] Escolher um plano gera fatura pendente e cancela a anterior que estiver pendente.
- [x] Confirmar pagamento é idempotente: uma fatura paga não ativa o plano duas vezes.
- [x] Comandos da equipe comercial: `faturas:pendentes` lista o que cobrar; `fatura:pagar {id} --referencia=` confirma o pagamento recebido fora do sistema.
- [x] `assinatura:ativar` passa a registrar uma fatura paga, para manter o histórico.
- [x] A tela de assinatura mostra a fatura pendente com o valor, o vencimento e como pagar, o histórico de faturas e o valor de cada plano para a quantidade atual de usuários.

### 3. Carência, só leitura e suspensão

- [x] Situações novas no modelo: carência e suspensa, com datas de início do só leitura e da suspensão.
- [x] O bloqueio de gravação considera a carência.
- [x] A suspensão bloqueia também a leitura, com página própria que explica o que houve e, para o admin, leva aos planos e faturas.
- [x] A faixa do topo do app avisa carência, só leitura com a data da suspensão e fatura pendente.

### 1b. Assentos e troca de plano (após a revisão de 24/10/2026)

- [x] Plano pago cobre os assentos da fatura; adicionar usuário acima disso pede compra de assentos.
- [x] Fatura pendente limita usuários, funis e leads ao que ela cobre.
- [x] Upgrade imediato com crédito proporcional; plano menor, menos usuários ou ciclo mais curto só na janela de renovação.
- [x] A tela escolhe a quantidade de usuários e confirma com a prévia do servidor: tipo, crédito e valor final.
- [x] Retomar a renovação dentro da janela gera a fatura na hora.
- [x] Fatura de upgrade vencida não impede a renovação.
- [x] Fatura do teste vence no fim do teste.
- [x] Aviso que falha ao enviar não conta como enviado e é tentado de novo.

### 4. Cancelar e retomar a renovação

- [x] Botões na tela de assinatura, só para quem gerencia a empresa.
- [x] Cancelar também cancela a fatura de renovação pendente.

### 5. Rotina diária e e-mails

- [x] Comando `assinaturas:rotina` agendado todo dia. Ele gera a fatura de renovação 7 dias antes do vencimento e envia os avisos devidos.
- [x] Cada aviso é enviado uma vez só por empresa e por prazo, mesmo se a rotina rodar duas vezes ou atrasar um dia.
- [x] Avisos aos admins da empresa:

| Aviso | Quando |
|---|---|
| Fatura gerada | Ao contratar e na renovação, 7 dias antes |
| Teste acabando | 7, 3 e 1 dia antes do fim do teste |
| Fatura vencendo | 3 e 1 dia antes do vencimento, se a fatura ainda estiver pendente |
| Plano termina | 7 dias antes do fim, quando a renovação foi cancelada |
| Teste encerrado | No dia em que o teste termina |
| Pagamento atrasado | No vencimento do plano pago, início da carência |
| Acesso limitado | Quando a gravação é bloqueada |
| Suspensão próxima | 7 dias antes da suspensão |
| Acesso suspenso | No dia da suspensão |
| Pagamento confirmado | Quando a fatura é paga |

- [x] A equipe comercial recebe um e-mail a cada fatura gerada, no endereço de `COMERCIAL_EMAIL`, com o comando para confirmar o pagamento.
- [x] Instruções de pagamento configuráveis em `COMERCIAL_PAGAMENTO_INSTRUCOES`, exibidas na tela e nos e-mails.

### Onde está no código

| Parte | Arquivo |
|---|---|
| Situações, carência e suspensão | `app/Models/Assinatura.php` |
| Limites, ativação, cancelamento | `app/Services/Assinaturas/Assinaturas.php` |
| Faturas: cotar, gerar, pagar | `app/Services/Assinaturas/Faturamento.php` |
| Avisos e destinatários | `app/Services/Assinaturas/AvisosDeAssinatura.php` |
| Textos dos e-mails | `app/Mail/AvisoDeAssinaturaMail.php` |
| Bloqueio e suspensão por requisição | `app/Http/Middleware/ExigirAssinatura.php` |
| Rotina diária | `app/Console/Commands/RotinaDeAssinaturas.php` |
| Comandos da equipe comercial | `fatura:pagar`, `faturas:pendentes`, `assinatura:ativar` |
| Telas | `Configuracoes/Assinatura.vue`, `Assinatura/Suspensa.vue`, faixa em `utils/faixaDaAssinatura.js` |
| Testes | `tests/Feature/Assinaturas/*`, `tests/js/pages/configuracoes/Assinatura.test.js`, `tests/js/utils/faixaDaAssinatura.test.js` |

## Enquadramento fiscal: MEI não pode vender SaaS

**A atividade do produto não está na lista do MEI.** A lista oficial de ocupações permitidas ao MEI é o Anexo XI da Resolução CGSN 140/2018, na versão publicada no site do Simples Nacional e consultada em 26/10/2026. Nela, as únicas ocupações de informática são:

| Ocupação | CNAE |
|---|---|
| Comerciante de equipamentos e suprimentos de informática | 4751-2/01 |
| Recarregador de cartuchos | 4751-2/02 |
| Instalador de rede de computadores | 6190-6/99 |
| Proprietário de sala de acesso à internet | 8299-7/07 |
| Instrutor de informática | 8599-6/03 |
| Técnico de manutenção de computador | 9511-8/00 |

Nenhuma é de desenvolvimento ou licenciamento de programas de computador. Os códigos desse grupo começam com 62. Segundo guias de contabilidade, SaaS padronizado se enquadra no CNAE 6203-1/00, desenvolvimento e licenciamento de programas não customizáveis. Não existe CNAE próprio para SaaS.

**O que isso muda:**

- Vender o CRM por um MEI fica fora do enquadramento. Isso pode levar ao desenquadramento de ofício, com cobrança retroativa dos impostos.
- Os intermediadores conferem o CNAE. O Asaas, por exemplo, exige CNAE compatível com o uso para liberar o Pix Automático.
- O limite do MEI em 2026 é de R$ 81 mil por ano, ou R$ 6.750 por mês. Isso cabe em cerca de 25 clientes de R$ 267 por mês. Há projetos no Congresso para aumentar esse teto, mas as fontes divergem sobre a situação deles, e nenhum aparece confirmado em vigor.
- O Pix Automático exige CNPJ com pelo menos 6 meses de atividade, segundo a documentação do Inter e o Asaas.

**Recomendação:** abrir uma ME no Simples Nacional, ou desenquadrar o MEI para ME, com o CNAE 6203-1/00. Faça isso com um contador, que define o anexo do Simples pelo Fator R e confirma se a idade do CNPJ atual conta para os 6 meses do Pix Automático. Enquanto o CNPJ não tiver 6 meses, cobre por Pix comum, boleto e cartão.

## Fase 2: gateway de pagamento

### Opções com recorrência e as menores taxas

Tarifas padrão publicadas pelos próprios intermediadores, consultadas em 26/10/2026. Valem para qualquer CNPJ: nenhuma das páginas traz condição especial para MEI. Contratos negociados podem ter taxas menores.

| Intermediador | Pix | Pix Automático | Boleto | Cartão à vista | Assinatura | Observação |
|---|---|---|---|---|---|---|
| **Asaas** | R$ 1,99; R$ 0,99 nos 3 primeiros meses | Mesma tarifa do Pix, na API desde 26/01/2026 | R$ 1,99; R$ 0,99 nos 3 primeiros meses | 2,99% + R$ 0,49 | Sem tarifa extra | Sem mensalidade. Nota fiscal a R$ 0,49. Aviso por WhatsApp a R$ 0,55 |
| **Efí** | 1,19% | R$ 3,50 | R$ 3,45 | 3,49%, recebe em até 31 dias | Recorrência gratuita | Sem mensalidade |
| **Pagar.me** Essencial | 0,99% | Não consta na página de ofertas | Não publicado | 4,19% | Venda por assinatura incluída | Sem mensalidade. Pode reter até 30 dias o pagamento de vendedor novo |
| **Stripe** | 1,19%, só por convite | Não consta na página de preços | R$ 3,45 | 3,99% + R$ 0,39 | Mais 0,7% do Billing | A API mais completa e o custo mais alto |
| **Inter Empresas** | Não publicado | API e webhook, tarifa não publicada | Não publicado | Não publicado | Pix Automático | Conta PJ gratuita. Pedir proposta |

Mercado Pago e PagBank ficaram de fora porque as páginas oficiais de tarifa bloquearam a consulta. Comparadores citam números divergentes para os dois.

### Quanto custa cada fatura

Tarifa de cada meio para três faturas típicas do produto:

| Meio | Inicial, 1 usuário, mensal: R$ 49 | Profissional, 3 usuários, mensal: R$ 267 | Profissional, 3 usuários, anual: R$ 2.484 |
|---|---|---|---|
| Asaas, Pix ou Pix Automático | R$ 1,99 | R$ 1,99 | R$ 1,99 |
| Asaas, cartão | R$ 1,96 | R$ 8,47 | R$ 74,76 |
| Efí, Pix | R$ 0,58 | R$ 3,18 | R$ 29,56 |
| Efí, Pix Automático | R$ 3,50 | R$ 3,50 | R$ 3,50 |
| Efí, cartão | R$ 1,71 | R$ 9,32 | R$ 86,69 |
| Pagar.me, Pix | R$ 0,49 | R$ 2,64 | R$ 24,59 |
| Pagar.me, cartão | R$ 2,05 | R$ 11,19 | R$ 104,08 |
| Stripe, Pix com Billing | R$ 0,93 | R$ 5,05 | R$ 46,95 |
| Stripe, cartão com Billing | R$ 2,69 | R$ 12,91 | R$ 116,89 |

A tarifa fixa do Asaas fica mais barata que o percentual da Efí a partir de R$ 167 por fatura, e mais barata que a do Pagar.me a partir de R$ 201.

### Recomendação

1. **Asaas como intermediador.** Tem Pix Automático na API sem custo extra, emissão de nota fiscal e nenhuma mensalidade. No cartão, é o mais barato da tabela para faturas acima de R$ 98.
2. **Pix Automático como meio padrão da renovação.** O cliente autoriza uma vez e a cobrança sai sozinha todo ciclo, por R$ 1,99. Cartão fica como alternativa.
3. **Empurrar o plano anual no Pix.** Uma fatura anual custa R$ 1,99 de tarifa. As 12 faturas mensais equivalentes custam R$ 23,88.
4. **Boleto só a pedido.** Custa o mesmo que o Pix no Asaas, mas compensa mais devagar e não renova sozinho.
5. **Conferir as taxas na conta antes de integrar.** O Asaas avisa que as taxas publicadas são as padrão e que o contrato pode ter outras.

Para faturas pequenas, como o Inicial mensal de 1 usuário, o Pix percentual da Efí ou do Pagar.me sai mais barato. Não compensa ter dois intermediadores por essa diferença enquanto a base for pequena.

### O que muda no código quando o gateway entrar

- [ ] Ao gerar a fatura, criar a cobrança no gateway e guardar a referência na fatura.
- [ ] Mostrar o QR Code do Pix, o boleto ou o link do cartão na tela e no e-mail da fatura.
- [ ] Rota de webhook sem sessão, que valida a assinatura do gateway e chama a mesma confirmação de pagamento usada hoje pelo comando `fatura:pagar`.
- [ ] Tratar estorno e contestação: cancelar a fatura e devolver a assinatura ao estado anterior.
- [ ] Cartão recorrente: o gateway cobra sozinho na renovação, e a rotina diária deixa de gerar a fatura manual para essas empresas.
- [ ] Cobrança proporcional de usuários adicionados no meio do ciclo, se o gateway suportar.
- [ ] Desligar os comandos manuais ou restringi-los a casos de exceção.
- [ ] No Asaas: cadastrar cada empresa como cliente, criar a cobrança de cada fatura ou a autorização de Pix Automático, e tratar os avisos de pagamento confirmado, recebido, estornado e contestado. Conferir os nomes dos eventos na documentação da API.
- [ ] Emitir a nota fiscal de serviço quando o pagamento for confirmado.

## Operação enquanto não há gateway

1. O agendador precisa rodar todo minuto no servidor: `* * * * * php artisan schedule:run`. A rotina `assinaturas:rotina` roda às 08:00 de Brasília.
2. Os e-mails saem pela fila: `php artisan queue:work` precisa estar no ar, e `MAIL_MAILER` configurado.
3. Preencher no `.env`: `COMERCIAL_EMAIL`, `COMERCIAL_WHATSAPP` e `COMERCIAL_PAGAMENTO_INSTRUCOES`, por exemplo com a chave Pix e para onde enviar o comprovante.
4. Ao receber um pagamento, rodar `php artisan faturas:pendentes`, achar a fatura e rodar `php artisan fatura:pagar <id> --referencia="pix 24/10"`.

## Pendências conhecidas

Achados menores da revisão, deixados para depois de propósito:

- Dois cliques simultâneos em contratar podem gerar duas faturas pendentes ou um erro em um deles. Não dá acesso sem pagar.
- O vencimento mensal escorrega: um ciclo que vence no dia 31 passa para o dia 28 depois de fevereiro e fica nele.
- As datas são calculadas em UTC. O teste que começa entre 21h e meia-noite de Brasília termina 3 horas antes do dia mostrado.
- A faixa do topo e o assunto do e-mail de fatura vencendo contam os dias até o fim do plano, não até o vencimento da fatura. Só difere em fatura de upgrade.
- Na janela de renovação, uma troca paga antes do vencimento passa a valer no pagamento, até 7 dias antes do fim do ciclo atual.
- Assinaturas ativadas antes desta versão não guardam o valor do ciclo, então não geram crédito numa troca.

## Fora deste plano

- Cupons, descontos e programa de indicação.
- Mudança de preço para clientes antigos.
- Exclusão definitiva dos dados de empresas suspensas há muito tempo, que exige política de retenção e LGPD.

## Fontes consultadas em 26/10/2026

- [Asaas: preços e taxas](https://www.asaas.com/precos-e-taxas)
- [Asaas: liberação pública do Pix Automático na API](https://docs.asaas.com/changelog/libera%C3%A7%C3%A3o-p%C3%BAblica-do-pix-autom%C3%A1tico)
- [Asaas: Pix Automático, custo e requisitos](https://blog.asaas.com/release/pix-automatico/)
- [Efí: tarifas](https://sejaefi.com.br/tarifas)
- [Pagar.me: ofertas](https://www.pagar.me/ofertas)
- [Stripe: preços no Brasil](https://stripe.com/br/pricing)
- [Inter Empresas: Pix Automático](https://inter.co/empresas/pix-automatico/) e [documentação da API](https://developers.inter.co/references/pix-automatico)
- [Receita Federal: Anexo XI da Resolução CGSN 140, ocupações permitidas ao MEI](https://www8.receita.fazenda.gov.br/simplesnacional/arquivos/manual/anexo_xi.pdf)
- [Contabilizei: profissional de TI pode ser MEI?](https://www.contabilizei.com.br/contabilidade-online/profissional-de-ti-pode-ser-mei/)
- [Fisco: qual o CNAE para SaaS](https://fisco.com.br/artigos/cnae-saas/)
- [Limite do MEI em 2026](https://contagilpb.com.br/mei/limite-faturamento-mei-2026) e [projeto de aumento do teto](https://www.seudinheiro.com/2026/seu-negocio/mei-faturamento-subira-para-r-130-mil-entenda-o-que-esta-em-jogo-no-projeto-que-pode-sair-do-papel-em-breve-na-camara-giov/)
- [Celcoin: tarifa do Pix Automático é definida por cada instituição](https://celcoin.com.br/articles/pix-automatico-para-empresas-clientes/)
