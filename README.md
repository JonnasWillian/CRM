# Sistema de gerenciamento de Leads

CRM de prospecção: lista e detalha leads, acompanha o estágio de cada um num
Kanban, guarda anotações e documentos, e registra os projetos e contratos
fechados com eles. O sistema é **multi-tenant** — cada empresa cliente enxerga
apenas os próprios dados.

## Stack

| Camada | Tecnologia |
|---|---|
| Backend | PHP 8.2, Laravel 11 |
| Frontend | Vue 3 + Inertia 2, Vite 6, Tailwind 3 |
| Banco | MySQL 8 |
| Autenticação | Laravel Breeze + Sanctum (`statefulApi`) |

## Requisitos

- PHP **8.2** com as extensões `mbstring`, `pdo_mysql` e `intl`
- Composer 2
- Node 20 e npm
- MySQL 8 acessível (o projeto assume `127.0.0.1:3306`)

## Instalação

```bash
git clone <repo> && cd GerenciadorDeUsuariosViewJs

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Edite o `.env`: troque `DB_CONNECTION=sqlite` por `mysql` e **descomente** as
linhas `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD`, que
vêm comentadas. Crie o banco e rode as migrations:

```bash
mysql -u root -p -e "CREATE DATABASE CRMLeader"
php artisan migrate
```

Se for usar o disco local para anexos, em vez de um serviço de storage externo,
crie o link simbólico:

```bash
php artisan storage:link
```

## Rodando em desenvolvimento

```bash
composer run dev
```

Sobe servidor, worker de fila, logs (`pail`) e Vite de uma vez. Para rodar só o
frontend, `npm run dev`; para gerar os assets de produção, `npm run build`.

## Testes

```bash
php artisan test
```

> **A suíte usa um banco separado do de desenvolvimento.** `php artisan test` lê
> o `.env.testing` (`CRMLeader_test`), enquanto `php artisan migrate` e os demais
> comandos leem o `.env` (`CRMLeader`). Isso é proposital: a suíte usa
> `RefreshDatabase`, que **apaga e recria o banco a cada execução** — apontá-la
> para o banco de dev destruiria seus dados.
>
> A consequência prática atrapalha na hora de depurar: ao conferir manualmente o
> efeito de uma migration, inspecione `CRMLeader`, não `CRMLeader_test`. Os dois
> divergem, e olhar o banco errado leva a diagnóstico falso.

Para preparar o ambiente de teste, copie `.env.testing.example` para
`.env.testing` e gere uma chave própria:

```bash
cp .env.testing.example .env.testing
php artisan key:generate --env=testing
```

O CI (GitHub Actions, `.github/workflows/tests.yml`) roda a suíte a cada push e
pull request, contra um MySQL 8 de serviço.

## Arquitetura

### Vocabulário: `User` e `Usuario` são coisas diferentes

A distinção mais fácil de errar no código:

| Model | Tabela | O que é |
|---|---|---|
| `User` | `users` | O **agente** que faz login no sistema |
| `Usuario` | `usuarios` | O **lead** — o contato prospectado |

Daí virem nomes como `usuarios.user_id`: o agente dono daquele lead. `Tarefa`
expõe a relação como `lead()` justamente para reduzir a ambiguidade.

### Multi-tenancy

Banco único, com `tenant_id` denormalizado em todas as tabelas de domínio e
isolamento aplicado por Global Scope do Eloquent. Sem pacote externo.

| Peça | Papel |
|---|---|
| `App\Support\Tenancy\CurrentTenant` | Singleton que guarda o tenant ativo do request |
| `App\Models\Scopes\TenantScope` | Global Scope que filtra toda query por `tenant_id` |
| `App\Models\Concerns\BelongsToTenant` | Registra o scope e preenche `tenant_id` ao criar |
| `App\Http\Middleware\IdentifyTenant` | Alias `tenant`; resolve o tenant a partir do usuário logado |
| `App\Services\TenantBootstrapper` | Semeia tags e status padrão para um tenant novo |

O `TenantScope` é **fail-closed**: sem tenant ativo ele lança `RuntimeException`
em vez de devolver tudo sem filtro. Por isso jobs e commands, que rodam fora do
ciclo HTTP, precisam chamar `CurrentTenant::set()` explicitamente.

Onze models de domínio usam a trait `BelongsToTenant`. `User` é a exceção
deliberada: recebe só a relação `tenant()`, sem o Global Scope — com ele, o login
quebraria antes mesmo de autenticar.

As rotas de `/api` ficam sob `middleware(['auth', 'tenant'])`. `IdentifyTenant`
roda depois do `auth` e responde 401 sem sessão, ou 403 se o usuário não tiver
tenant (inclusive quando o tenant foi soft-deleted).

### Estágios do funil e status de projeto

`tags` são os estágios do Kanban; `status` são os estados de um projeto. Ambos
são por tenant e usam **colunas semânticas**, não IDs fixos:

- `tags.is_active` — estágio ativo (em andamento) ou arquivado
- `status.is_won` / `status.is_lost` — projeto ganho ou perdido; nenhum dos dois
  significa em aberto, que é o que o query scope `Statu::scopeOpen()` filtra

Ambos usam soft delete, e as FKs `usuarios.tag_id` e `projetos.status_id` são
`restrict` — apagar um estágio em uso é impedido pelo banco, em vez de arrastar
leads e projetos junto. Arquivar um estágio que ainda tem leads mantém a coluna
visível no Kanban, marcada como somente-saída, até que o último lead seja movido.

Unicidade de email de lead é **por tenant** (`UNIQUE(tenant_id, email)`): o mesmo
contato pode ser lead de duas empresas diferentes.

## Documentação de design

Decisões arquiteturais ficam em `docs/superpowers/specs/`.
