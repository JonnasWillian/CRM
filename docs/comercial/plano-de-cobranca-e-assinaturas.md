# Plano de cobrança e assinaturas

Data: 24/10/2026. Complementa a [análise comercial](analise-comercial-e-seo.md), bloqueador B1 (cobrança).

**Situação:** a Fase 1 está implementada e testada. Falta a Fase 2, que depende da escolha do gateway de pagamento.

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
- **Usuários que entram no meio do ciclo são cobrados na renovação seguinte.** A fatura de renovação usa a contagem do dia em que é gerada. Cobrança proporcional fica para a fase do gateway.
- **Renovação paga antes do fim ou durante a carência continua do vencimento anterior.** Paga depois que o acesso foi limitado, conta a partir do pagamento.
- **Pagar durante o teste grátis não encurta o teste.** O ciclo pago começa quando o teste termina.
- **Troca de plano no meio do ciclo** vale a partir do pagamento e soma o novo ciclo ao vencimento atual. Sem crédito proporcional nesta fase.
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

## Fase 2: gateway de pagamento

Depende de escolher o gateway. Critérios para a escolha:

- Pix, boleto e cartão com cobrança recorrente.
- Aviso de pagamento por webhook assinado.
- Emissão de nota fiscal de serviço junto da cobrança.
- Taxas por meio de pagamento e prazo de recebimento.
- API e painel em português.

O que muda no código quando o gateway entrar:

- [ ] Ao gerar a fatura, criar a cobrança no gateway e guardar a referência na fatura.
- [ ] Mostrar o QR Code do Pix, o boleto ou o link do cartão na tela e no e-mail da fatura.
- [ ] Rota de webhook sem sessão, que valida a assinatura do gateway e chama a mesma confirmação de pagamento usada hoje pelo comando `fatura:pagar`.
- [ ] Tratar estorno e contestação: cancelar a fatura e devolver a assinatura ao estado anterior.
- [ ] Cartão recorrente: o gateway cobra sozinho na renovação, e a rotina diária deixa de gerar a fatura manual para essas empresas.
- [ ] Cobrança proporcional de usuários adicionados no meio do ciclo, se o gateway suportar.
- [ ] Desligar os comandos manuais ou restringi-los a casos de exceção.

## Operação enquanto não há gateway

1. O agendador precisa rodar todo minuto no servidor: `* * * * * php artisan schedule:run`. A rotina `assinaturas:rotina` roda às 08:00 de Brasília.
2. Os e-mails saem pela fila: `php artisan queue:work` precisa estar no ar, e `MAIL_MAILER` configurado.
3. Preencher no `.env`: `COMERCIAL_EMAIL`, `COMERCIAL_WHATSAPP` e `COMERCIAL_PAGAMENTO_INSTRUCOES`, por exemplo com a chave Pix e para onde enviar o comprovante.
4. Ao receber um pagamento, rodar `php artisan faturas:pendentes`, achar a fatura e rodar `php artisan fatura:pagar <id> --referencia="pix 24/10"`.

## Fora deste plano

- Cupons, descontos e programa de indicação.
- Mudança de preço para clientes antigos.
- Exclusão definitiva dos dados de empresas suspensas há muito tempo, que exige política de retenção e LGPD.
