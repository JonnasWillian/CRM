# Deploy do UserFlow em um servidor

Este guia mostra como colocar o UserFlow em produção num servidor próprio (VPS) e como atualizar depois. Os comandos são para Ubuntu 22.04 ou 24.04 com Nginx, PHP-FPM, MySQL e Supervisor. Em outro sistema, os passos são os mesmos e mudam os nomes dos pacotes.

O que foi conferido no projeto: as versões exigidas, as variáveis de ambiente, as tarefas agendadas e os comandos `artisan`. O que **não** foi testado num servidor real: os arquivos de configuração do Nginx e do Supervisor abaixo. Trate-os como ponto de partida.

## Resumo: o que precisa estar rodando

| Peça | Para quê | Sem ela |
|---|---|---|
| Nginx + PHP-FPM 8.2 ou mais novo | Servir o site | Nada funciona |
| MySQL 8 | Banco de dados | Nada funciona |
| HTTPS com domínio | Login seguro e webhook do Asaas | O Asaas não entrega avisos de pagamento |
| Worker da fila (`queue:work`) | E-mails do sistema, importação de planilha, remoção de cobranças | Convites e avisos não saem; importações ficam paradas |
| Agendador (cron de 1 minuto) | Rotina de assinaturas, conciliação do Asaas, limpezas | Faturas de renovação não são geradas; pagamento sem aviso não é confirmado |
| Envio de e-mail (SMTP) | Convites, redefinição de senha, avisos de cobrança | Ninguém recebe e-mail do sistema |
| Canal do suporte (FormSubmit ou SMTP) | Chamados do assistente de ajuda | Os chamados ficam só no banco |

## 1. Requisitos do servidor

- **PHP 8.2 ou mais novo**, com as extensões `bcmath`, `ctype`, `curl`, `fileinfo`, `gd`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml` e `zip`.
- **Composer 2**.
- **Node.js 20 ou mais novo e npm**, só para gerar os arquivos do front (`npm run build`). Se o build for feito em outra máquina e a pasta `public/build` for enviada junto, o servidor não precisa de Node.
- **MySQL 8**.
- **Nginx**, **Supervisor** e **Certbot** (certificado HTTPS gratuito).
- Um **domínio** apontando para o IP do servidor.

```bash
sudo apt update
sudo apt install -y nginx mysql-server supervisor certbot python3-certbot-nginx git unzip \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-bcmath php8.3-gd php8.3-intl
```

Limites de upload: a importação de planilha aceita arquivos de até 10 MB. Deixe `upload_max_filesize` e `post_max_size` do PHP, e `client_max_body_size` do Nginx, em pelo menos 12 MB.

## 2. Banco de dados

```sql
CREATE DATABASE userflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'userflow'@'localhost' IDENTIFIED BY 'uma-senha-forte';
GRANT ALL PRIVILEGES ON userflow.* TO 'userflow'@'localhost';
FLUSH PRIVILEGES;
```

## 3. Código e dependências

```bash
sudo mkdir -p /var/www/userflow && sudo chown $USER:www-data /var/www/userflow
git clone https://github.com/JonnasWillian/CRM.git /var/www/userflow
cd /var/www/userflow

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate
```

Permissões: o PHP precisa escrever em `storage` e `bootstrap/cache`.

```bash
sudo chown -R $USER:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

Os arquivos enviados pelos usuários (documentos de leads, planilhas importadas) ficam em `storage/app/private`. Eles **não** são públicos: todo download passa pelo sistema, que confere a permissão. Inclua essa pasta no backup.

## 4. Arquivo `.env` de produção

Edite `/var/www/userflow/.env`. Os valores abaixo são os que mudam em relação ao exemplo.

### Aplicação

```dotenv
APP_NAME=UserFlow
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seudominio.com.br
APP_TIMEZONE=America/Sao_Paulo
APP_LOCALE=pt_BR

LOG_LEVEL=warning
```

`APP_DEBUG=false` é obrigatório: com `true`, uma tela de erro mostra senhas e chaves a qualquer visitante.

### Banco, sessão, cache e fila

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=userflow
DB_USERNAME=userflow
DB_PASSWORD=uma-senha-forte

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database

SANCTUM_STATEFUL_DOMAINS=seudominio.com.br
```

`SANCTUM_STATEFUL_DOMAINS` precisa ter o domínio do site. Sem ele, as telas abrem, mas as ações que usam a API (mover lead, salvar tarefa) respondem "não autenticado".

### E-mail do sistema (SMTP)

Convites de equipe, redefinição de senha e avisos de cobrança saem por SMTP e passam pela fila. Use um serviço de envio transacional (Amazon SES, Brevo, Mailgun, Resend, ou o SMTP do seu provedor de e-mail).

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.seuprovedor.com
MAIL_PORT=465
MAIL_USERNAME=usuario-do-smtp
MAIL_PASSWORD=senha-do-smtp
MAIL_FROM_ADDRESS=nao-responda@seudominio.com.br
MAIL_FROM_NAME="${APP_NAME}"
```

Para os e-mails não caírem em spam, configure no DNS do domínio os registros **SPF** e **DKIM** que o serviço de envio indicar.

Com `MAIL_MAILER=log` (o valor do ambiente de desenvolvimento), nenhum e-mail sai: todos são só gravados em `storage/logs/laravel.log`.

### Chamados do assistente de ajuda

O assistente de ajuda (botão flutuante do app) grava todo chamado no banco e, quando a pessoa pede o suporte, avisa a caixa de `SUPORTE_EMAIL`. Há dois canais:

```dotenv
SUPORTE_EMAIL=contato@devscody.com
SUPORTE_CANAL=formsubmit
SUPORTE_FORMSUBMIT_DESTINO=
```

| `SUPORTE_CANAL` | Como entrega | Precisa de | Observação |
|---|---|---|---|
| `formsubmit` | O serviço formsubmit.co envia o e-mail | Ativar o endereço uma vez (abaixo) | Não usa SMTP nem fila. Os dados do chamado passam por esse serviço externo |
| `mail` | O SMTP do próprio sistema | SMTP configurado e worker da fila rodando | Os dados não passam por terceiros |

**Ativação do FormSubmit.** No primeiro envio para um endereço, o FormSubmit não entrega o chamado: ele manda para esse endereço um e-mail com o botão **"Activate Form"**. Depois do clique, os envios passam a chegar. O endereço `contato@devscody.com` já foi ativado em 23/11/2026. Se `SUPORTE_EMAIL` mudar, o novo endereço precisa ser ativado do mesmo jeito.

- O e-mail de ativação traz também um código que pode substituir o endereço na chamada. Para usá-lo, coloque-o em `SUPORTE_FORMSUBMIT_DESTINO`.
- Um chamado que não foi entregue (serviço fora do ar, endereço ainda não ativado) fica gravado e marcado como não entregue. Para tentar de novo: `php artisan suporte:reenviar`. Para reenviar um chamado específico: `php artisan suporte:reenviar 12`.
- A pessoa que abriu o chamado vê o protocolo mesmo quando a entrega falha. Por isso vale agendar o reenvio (seção 7).
- Com o canal `formsubmit`, registre na política de privacidade que os dados do chamado são processados por esse serviço.

### Comercial e dados da empresa

```dotenv
COMERCIAL_EMAIL=comercial@seudominio.com.br
COMERCIAL_WHATSAPP=5511999998888
COMERCIAL_RAZAO_SOCIAL="Sua Razão Social Ltda"
COMERCIAL_CNPJ=00.000.000/0001-00
COMERCIAL_CIDADE="São Paulo, SP"
COMERCIAL_DPO_EMAIL=privacidade@seudominio.com.br
COMERCIAL_GA4_ID=
```

`COMERCIAL_EMAIL` recebe os avisos de fatura gerada, os alertas de pagamento e as respostas do questionário de primeiro acesso. Esses dados também aparecem no site, nos Termos e na Política de Privacidade.

### Pagamentos (Asaas)

```dotenv
ASAAS_AMBIENTE=producao
ASAAS_API_KEY='$aact_prod_...'
ASAAS_WEBHOOK_TOKEN=um-texto-aleatorio-de-32-a-255-caracteres-sem-espacos
```

- A chave vai **entre aspas simples**, porque começa com `$`.
- Com `APP_ENV=production`, o sistema recusa `ASAAS_AMBIENTE` diferente de `producao`.
- Gere o token do webhook com `openssl rand -hex 32`.
- Sem `ASAAS_API_KEY`, o pagamento online fica desligado e as faturas são confirmadas à mão (`php artisan fatura:pagar`).

O passo a passo completo do Asaas está em [docs/comercial/plano-de-cobranca-e-assinaturas.md](../comercial/plano-de-cobranca-e-assinaturas.md).

## 5. Migrações e cache

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

As migrações já criam os papéis e as permissões (admin, gestor, vendedor). **Não rode `db:seed` em produção.**

Depois de `config:cache`, toda mudança no `.env` só vale depois de rodar `php artisan config:cache` de novo.

## 6. Nginx e HTTPS

`/etc/nginx/sites-available/userflow`:

```nginx
server {
    listen 80;
    server_name seudominio.com.br;
    root /var/www/userflow/public;
    index index.php;

    client_max_body_size 12M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/userflow /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d seudominio.com.br
```

A raiz do site é a pasta `public`. Nunca aponte o Nginx para a raiz do projeto: isso exporia o `.env`.

Se houver um proxy na frente do servidor (Cloudflare, balanceador de carga), o projeto ainda não tem os proxies confiáveis configurados em `bootstrap/app.php`. Sem isso, o Laravel pode gerar links em `http` e registrar o IP do proxy no lugar do IP do visitante. Configure `trustProxies` antes de usar um proxy.

## 7. Fila e agendador

### Worker da fila (Supervisor)

`/etc/supervisor/conf.d/userflow-fila.conf`:

```ini
[program:userflow-fila]
command=php /var/www/userflow/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/var/www/userflow
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/userflow/storage/logs/fila.log
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status userflow-fila
```

### Agendador (cron)

```bash
sudo crontab -u www-data -e
```

```cron
* * * * * cd /var/www/userflow && php artisan schedule:run >> /dev/null 2>&1
```

O que o agendador roda:

| Comando | Quando | O que faz |
|---|---|---|
| `assinaturas:rotina` | Todo dia, 8h (Brasília) | Gera a fatura de renovação e envia os avisos de vencimento, carência e suspensão |
| `asaas:conciliar` | De hora em hora | Confirma pagamentos cujo aviso do Asaas não chegou |
| `activity:limpar` | Todo dia | Apaga o histórico de atividades além do prazo de retenção |
| `importacoes:limpar` | Todo dia | Apaga arquivos de importação antigos |

O reenvio de chamados de suporte não entregues **não** está agendado. Para agendar, acrescente em `routes/console.php`:

```php
Schedule::command('suporte:reenviar')->hourly();
```

## 8. Ligar o Asaas

Com o site no ar em HTTPS e o `.env` preenchido:

```bash
php artisan asaas:verificar   # confere a chave e o ambiente
php artisan asaas:webhook     # cadastra o webhook no Asaas, apontando para este servidor
```

No painel do Asaas, em Integrações, cadastre o IP do servidor na lista de IPs autorizados. Essa é a principal proteção da chave de API.

## 9. Conferir depois do deploy

1. Abrir `https://seudominio.com.br`: o site e a página de preços carregam.
2. Criar uma conta de teste em `/register`: o questionário de primeiro acesso aparece.
3. Convidar um e-mail seu em Configurações › Equipe: o convite chega (prova o SMTP e a fila).
4. Abrir o assistente de ajuda (botão no canto inferior direito) e enviar um chamado: o e-mail chega em `SUPORTE_EMAIL`.
5. `php artisan asaas:verificar`: responde "Conexão com o Asaas funcionando. Ambiente: produção."
6. `sudo supervisorctl status`: o worker está `RUNNING`.
7. `tail -n 50 storage/logs/laravel.log`: sem erros novos.

## 10. Atualizar para uma versão nova

```bash
cd /var/www/userflow
php artisan down

git pull origin master
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart

php artisan up
```

`queue:restart` é necessário: o worker mantém o código antigo na memória até ser reiniciado.

Faça um backup do banco antes de cada atualização que tenha migração.

## 11. Backup

- **Banco:** `mysqldump userflow | gzip > userflow-$(date +%F).sql.gz`, todo dia, guardado fora do servidor.
- **Arquivos dos usuários:** a pasta `storage/app/private`.
- **O `.env`:** guarde uma cópia num cofre de senhas. Sem a `APP_KEY`, as sessões e os dados criptografados se perdem.

## 12. Problemas comuns

| Sintoma | Causa provável | O que fazer |
|---|---|---|
| Nenhum e-mail chega | `MAIL_MAILER=log`, SMTP errado ou worker parado | Conferir o `.env`, rodar `config:cache`, ver `supervisorctl status` e `storage/logs/laravel.log` |
| E-mail do chamado de suporte não chega | Endereço não ativado no FormSubmit, ou canal `mail` sem SMTP | Procurar o e-mail "Activate Form" na caixa do suporte; depois, `php artisan suporte:reenviar` |
| Ações na tela respondem "não autenticado" | `SANCTUM_STATEFUL_DOMAINS` sem o domínio, ou `APP_URL` errada | Corrigir o `.env` e rodar `config:cache` |
| Tela em branco ou sem estilo | Faltou `npm run build`, ou `APP_URL` em `http` com o site em `https` | Rodar o build; corrigir a `APP_URL` |
| Erro 500 logo após o deploy | Permissão em `storage` ou `bootstrap/cache`, ou `.env` alterado sem `config:cache` | Ajustar as permissões; rodar `config:cache` |
| Pagamento feito e o plano não ativa | Webhook não cadastrado, token diferente ou agendador parado | `php artisan asaas:webhook`; conferir o cron; `php artisan asaas:conciliar` |
| Importação de planilha não anda | Worker da fila parado | `sudo supervisorctl restart userflow-fila` |
| Mudei o `.env` e nada mudou | Configuração em cache | `php artisan config:cache` |
