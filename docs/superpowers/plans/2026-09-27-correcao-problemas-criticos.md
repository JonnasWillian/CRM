# Correção na raiz dos problemas críticos P1–P8

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminar, na causa, os oito problemas de segurança e integridade levantados na análise crítica. Cada correção é provada por um teste escrito antes dela, e todo dado real é validado antes de uma migration tocá-lo.

**Architecture:** Cada problema ganha **uma fonte única de verdade**, e é ela que impede a volta do problema:
- `PoliticaDeUpload` para tipos e tamanho de arquivo;
- `ArquivoService` como dono do ciclo de vida do arquivo em disco privado;
- `RESTRICT` no banco mais `SoftDeletes`, em vez de `CASCADE`;
- `ExclusaoDeConta` para as regras de saída de um agente;
- `Telefone` e `TelefoneE164` para o formato do telefone;
- `LimitesDeTexto` para o tamanho dos textos;
- `ListagemDeLeads` para filtros e paginação no servidor;
- `AtividadeResource` para a timeline.

Os testes de prevenção (`SemCascataDestrutivaTest`, a rede `TodaRotaDeEscritaAutorizaTest`) travam a regressão.

**Tech Stack:** PHP 8.2, Laravel 11.36.1, MySQL 8 (strict), spatie/laravel-permission 6.25 (teams), spatie/laravel-activitylog 4.12, Inertia 2 + Vue 3.4, maska 3.1, PHPUnit 11.

**Spec:** `docs/analise/2026-09-27-problemas-criticos.md` (P1–P8), com as decisões de 2026-09-27 registradas abaixo.

## Decisões já tomadas (não reabrir)

- **P3:** a exclusão da própria conta é **bloqueada** enquanto o agente tiver leads (inclusive na lixeira) ou for o último admin do tenant. A mensagem diz o que falta resolver. A transferência de leads (L2) destrava isso no futuro.
- **P4:** soft delete em lead e projeto, com restauração **pela API**. A tela de lixeira fica fora deste plano.
- **P5:** telefone em **E.164** (`+5511999998888`) e **opcional**.
- **P7:** paginação e filtros no servidor para a lista do Dashboard, as métricas sem `whereIn` gigante e a timeline no activity log paginado. O Kanban continua carregando o funil inteiro.

## Global Constraints

- **PHP 8.2**: não usar sintaxe de 8.3+.
- **Laravel 11.36.1**: `->change()` em migration **não** preserva atributos omitidos; repita `nullable()`/`default()` sempre. `doctrine/dbal` não é necessário e não deve ser instalado.
- **Acesso negado responde 404**, nunca 403, em dado de carteira (`Response::denyAsNotFound()`).
- **`tenant_id` nunca entra em `$fillable`.**
- **Testes rodam contra `CRMLeader_test`**, fixado em `phpunit.xml`. Nunca apontar para `CRMLeader`. Inspeção de schema de teste é no `CRMLeader_test`; inspeção de dados reais é no `CRMLeader`.
- Toda tarefa termina com `php artisan test` **inteiro** verde. Tarefas que tocam `resources/js` também terminam com `npm run build` verde.
- Toda rota de escrita nova precisa passar em `tests/Feature/Autorizacao/TodaRotaDeEscritaAutorizaTest.php`: tem `$this->authorize(` no corpo, um FormRequest com `authorize()` real ou uma linha em `ISENTAS` com motivo.
- **Migration que toca dados** só roda em `CRMLeader` depois do backup da Tarefa 0 e do relatório `--dry-run` correspondente.
- **Não commitar.** Neste projeto quem commita é o dono do repositório, depois de revisar o diff. Os passos de commit existem no formato e devem ser **pulados**: rode os testes, deixe as mudanças na árvore e pare.
- **Trabalhar no diretório principal**, em `master`, sem git worktree.
- Mensagens ao usuário em português, no tom das existentes (frases curtas, sem ponto de exclamação).

## Review Focus

Cinco condições que a análise implica e que quebram na mão de quem usa. Cada uma tem teste na tarefa dona do código.

1. **Arquivo maior que o `upload_max_filesize` do PHP** (2M no ambiente local). O PHP descarta o upload antes da validação, e a resposta precisa ser um 422 dizendo "passa do limite de upload do servidor", não "o campo arquivo é obrigatório". → Tarefa 1.
2. **Link salvo para um lead que foi para a lixeira** (`/leads/{id}` no navegador e `/api/usuarioPerfil/{id}`). Deve dar 404 limpo, nunca 500 nem a tela quebrada. O mesmo vale para projetos, anotações e anexos pendurados nesse lead, também para o gestor. → Tarefa 6.
3. **Quick-add do Kanban sem telefone.** Agora que o campo é opcional, o front não pode mais bloquear o envio, e o servidor precisa aceitar. → Tarefa 9.
4. **Busca na lista com `%`, `_` ou parênteses** ("(11)", "50%"). Tem de ser tratada como texto literal, sem virar curinga do LIKE e sem erro. → Tarefa 14.
5. **Anexo salvo com `nome` vazio** (o front manda `''` quando o usuário não digita). O download precisa sair com um nome de arquivo válido, e não com `Content-Disposition` vazio. → Tarefa 3.

## Mapa de arquivos

| Responsabilidade | Arquivo | Tarefa |
|---|---|---|
| Regras de upload | Create `app/Support/Arquivos/PoliticaDeUpload.php` | 1 |
| Ciclo de vida do arquivo | Create `app/Services/Arquivos/ArquivoService.php`; Delete `app/Service/ArquivoService.php` | 2 |
| Download protegido | Create `app/Http/Controllers/DownloadDeArquivoController.php` | 3 |
| Migração para disco privado | Create `app/Console/Commands/MigrarArquivosParaPrivado.php` | 4 |
| Regra comum de dono | Create `app/Policies/Concerns/DonoDoLead.php` | 6 |
| E-mail único com lixeira | Create `app/Rules/EmailDeLeadDisponivel.php` | 6 |
| Saída de agente | Create `app/Services/Contas/ExclusaoDeConta.php` | 7 |
| Telefone | Create `app/Support/Telefone.php`, `app/Rules/TelefoneE164.php`, `app/Services/Leads/NormalizacaoDeTelefones.php`, `app/Console/Commands/NormalizarTelefones.php`, `resources/js/utils/telefone.js` | 8, 9 |
| Limites de texto | Create `app/Support/LimitesDeTexto.php` | 10 |
| Listagem de leads | Create `app/Queries/Leads/ListagemDeLeads.php`, `app/Http/Requests/ListagemDeLeadsRequest.php` | 14 |
| Timeline | Create `app/Http/Resources/AtividadeResource.php` | 16 |
| Fixtures de teste | Create `tests/Concerns/CenarioDeTenant.php` | 1 |

Migrations novas, com prefixo `2026_09_28_`:
- `100001_add_tamanho_e_mime_aos_anexos`
- `110001_soft_deletes_em_usuarios_e_projetos`
- `110002_trocar_cascade_por_restrict`
- `120001_telefone_como_texto_e164`
- `130001_textos_longos_como_text`
- `140001_indice_de_criacao_em_usuarios`

---

### Task 0: Linha de base, backup e relatório dos dados

Nada de código. Serve para ter certeza de que o ponto de partida está verde e de que os dados reais estão salvos e conhecidos antes de qualquer migration.

**Files:** nenhum no repositório. As saídas vão para o scratchpad da sessão (`$SCRATCH`, o diretório de trabalho temporário do agente).

- [ ] **Step 1: Suíte e build na linha de base**

Run: `php artisan test 2>&1 | tail -5 && npm run build 2>&1 | tail -3`
Expected: `Tests: N passed` (≈181) e build sem erro. **Se algo já estiver vermelho, anote o nome do teste e pare para avisar o usuário.** Nada deste plano deve ser culpado por uma falha que já existia.

- [ ] **Step 2: Backup do banco de dev e dos arquivos**

```bash
mysqldump -u root -p CRMLeader > "$SCRATCH/CRMLeader-antes-2026-09-28.sql"
tar -czf "$SCRATCH/storage-public-arquivos-antes.tgz" -C storage/app/public arquivos
ls -la "$SCRATCH"/CRMLeader-antes-2026-09-28.sql "$SCRATCH"/storage-public-arquivos-antes.tgz
```
Expected: os dois arquivos existem, com tamanho maior que zero.

- [ ] **Step 3: Relatório somente leitura do que as migrations vão tocar**

```bash
mysql -u root -p CRMLeader -e "
SELECT LENGTH(CAST(telefone AS CHAR)) AS digitos, COUNT(*) AS leads FROM usuarios GROUP BY digitos ORDER BY digitos;
SELECT 'anotacaos' AS tabela, COUNT(*) AS perto_do_limite FROM anotacaos WHERE CHAR_LENGTH(descricao) >= 250
UNION ALL SELECT 'projetoAnotacaos', COUNT(*) FROM projetoAnotacaos WHERE CHAR_LENGTH(descricao) >= 250;
SELECT COUNT(*) AS arquivos_linhas FROM arquivos; SELECT COUNT(*) AS anexos_linhas FROM projetoAnexos;
SELECT u.id, u.email, COUNT(l.id) AS leads FROM users u LEFT JOIN usuarios l ON l.user_id = u.id GROUP BY u.id, u.email;
SELECT mhr.tenant_id, COUNT(*) AS admins FROM model_has_roles mhr JOIN roles r ON r.id = mhr.role_id AND r.name = 'admin' GROUP BY mhr.tenant_id;
" | tee "$SCRATCH/relatorio-dados-antes.txt"
ls storage/app/public/arquivos | wc -l
```
Expected: o relatório fica salvo. Anote no fim dele:
- quantos telefones **não** têm 10 ou 11 dígitos (são os que a Tarefa 9 vai listar como inválidos);
- quantos arquivos existem em disco comparados às linhas do banco (19 em disco em 2026-09-27).

---

## Fase 1: Arquivos (P2, depois P1)

### Task 1: `PoliticaDeUpload`, a regra única de tipo e tamanho (P2)

**Causa raiz:** `ArquivoRequest` e `ProjetoAnexoRequest` validam só `['required','file']`, e o sistema não define em lugar nenhum quais arquivos aceita.

**Files:**
- Create: `tests/Concerns/CenarioDeTenant.php`
- Create: `app/Support/Arquivos/PoliticaDeUpload.php`
- Modify: `app/Http/Requests/ArquivoRequest.php` (`rules()`, `messages()`)
- Modify: `app/Http/Requests/ProjetoAnexoRequest.php` (`rules()`, `messages()`)
- Modify: `README.md` (seção de requisitos)
- Test: `tests/Feature/Arquivos/PoliticaDeUploadTest.php`

**Interfaces:**
- Produces:
  - `PoliticaDeUpload::MAX_KB` (int, 10240)
  - `PoliticaDeUpload::EXTENSOES` (list<string>)
  - `PoliticaDeUpload::regras(): array`
  - `PoliticaDeUpload::mensagens(string $campo = 'arquivo'): array`
- Produces (testes): o trait `Tests\Concerns\CenarioDeTenant`, com:
  - `novoTenant(): Tenant`
  - `ativar(Tenant $t): void`
  - `agente(Tenant $t, string $papel = 'vendedor'): User`
  - `lead(Tenant $t, User $dono, array $attrs = []): Usuario`
  - `projeto(Usuario $lead, array $attrs = []): Projeto`
  
  As tarefas seguintes usam essas assinaturas.

- [ ] **Step 1: Criar o trait de fixtures**

```php
<?php

namespace Tests\Concerns;

use App\Models\Funil;
use App\Models\Projeto;
use App\Models\Statu;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Spatie\Permission\PermissionRegistrar;

/**
 * O mesmo cenário que cada teste de autorização monta à mão: tenant ativo,
 * team do spatie apontado para ele, agentes com papel, lead no funil padrão.
 *
 * `ativar()` existe à parte porque testes com dois tenants precisam trocar o
 * tenant corrente entre um fixture e outro — o TenantScope é fail-closed e o
 * BelongsToTenant preenche tenant_id a partir dele.
 */
trait CenarioDeTenant
{
    protected function novoTenant(): Tenant
    {
        $tenant = Tenant::factory()->create();
        $this->ativar($tenant);

        return $tenant;
    }

    protected function ativar(Tenant $tenant): void
    {
        app(CurrentTenant::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
    }

    protected function agente(Tenant $tenant, string $papel = 'vendedor'): User
    {
        $this->ativar($tenant);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole($papel);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    protected function lead(Tenant $tenant, User $dono, array $attrs = []): Usuario
    {
        $this->ativar($tenant);
        $funil = Funil::where('is_default', true)->first()
            ?? Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);

        return Usuario::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'user_id' => $dono->id,
            'funil_id' => $funil->id,
        ], $attrs));
    }

    protected function projeto(Usuario $lead, array $attrs = []): Projeto
    {
        $status = Statu::factory()->create(['tenant_id' => $lead->tenant_id]);

        $projeto = new Projeto();
        $projeto->fill(array_merge([
            'nome' => 'Proposta inicial',
            'usuario_id' => $lead->id,
            'status_id' => $status->id,
        ], $attrs));
        $projeto->tenant_id = $lead->tenant_id;
        $projeto->save();

        return $projeto;
    }
}
```

- [ ] **Step 2: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Arquivos;

use App\Models\Projeto;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Arquivos\PoliticaDeUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * P2: o que entra no sistema como arquivo. A lista é de permitidos, não de
 * proibidos — um tipo novo e perigoso não pode passar só porque ninguém
 * lembrou de proibi-lo.
 */
class PoliticaDeUploadTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private User $dono;
    private Usuario $lead;
    private Projeto $projeto;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $tenant = $this->novoTenant();
        $this->dono = $this->agente($tenant);
        $this->lead = $this->lead($tenant, $this->dono);
        $this->projeto = $this->projeto($this->lead);
    }

    public static function recusados(): array
    {
        return [
            'html (XSS no mesmo domínio)' => ['pagina.html', 'text/html', 1],
            'svg (script embutido)' => ['logo.svg', 'image/svg+xml', 1],
            'executável com nome de pdf' => ['contrato.pdf', 'application/x-msdownload', 1],
            'acima do limite' => ['grande.pdf', 'application/pdf', PoliticaDeUpload::MAX_KB + 1],
        ];
    }

    public static function aceitos(): array
    {
        return [
            'pdf' => ['proposta.pdf', 'application/pdf'],
            'png' => ['foto.png', 'image/png'],
            'xlsx' => ['custos.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'csv' => ['lista.csv', 'text/csv'],
        ];
    }

    #[DataProvider('recusados')]
    public function test_anexo_de_lead_recusa(string $nome, string $mime, int $kb): void
    {
        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'arquivo' => UploadedFile::fake()->create($nome, $kb, $mime),
        ])->assertUnprocessable()->assertJsonValidationErrors('arquivo');

        $this->assertDatabaseCount('arquivos', 0);
    }

    #[DataProvider('recusados')]
    public function test_anexo_de_projeto_recusa(string $nome, string $mime, int $kb): void
    {
        $this->actingAs($this->dono)->postJson('/api/projetoAnexo', [
            'usuario_id' => $this->projeto->id,
            'arquivo' => UploadedFile::fake()->create($nome, $kb, $mime),
        ])->assertUnprocessable()->assertJsonValidationErrors('arquivo');

        $this->assertDatabaseCount('projetoAnexos', 0);
    }

    #[DataProvider('aceitos')]
    public function test_anexo_de_lead_aceita(string $nome, string $mime): void
    {
        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'arquivo' => UploadedFile::fake()->create($nome, 20, $mime),
        ])->assertSuccessful();

        $this->assertDatabaseCount('arquivos', 1);
    }

    #[DataProvider('aceitos')]
    public function test_anexo_de_projeto_aceita(string $nome, string $mime): void
    {
        $this->actingAs($this->dono)->postJson('/api/projetoAnexo', [
            'usuario_id' => $this->projeto->id,
            'arquivo' => UploadedFile::fake()->create($nome, 20, $mime),
        ])->assertSuccessful();

        $this->assertDatabaseCount('projetoAnexos', 1);
    }

    public function test_nome_com_mais_de_255_caracteres_e_recusado(): void
    {
        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'nome' => str_repeat('a', 256),
            'arquivo' => UploadedFile::fake()->create('a.pdf', 10),
        ])->assertUnprocessable()->assertJsonValidationErrors('nome');
    }

    /** Review Focus 1: o PHP descartou o upload antes de a validação ver o arquivo. */
    public function test_upload_barrado_pelo_php_explica_o_limite(): void
    {
        $caminho = tempnam(sys_get_temp_dir(), 'up');
        $barrado = new UploadedFile($caminho, 'grande.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true);

        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'arquivo' => $barrado,
        ])->assertUnprocessable()->assertJsonValidationErrors(['arquivo' => 'limite de upload do servidor']);
    }
}
```

- [ ] **Step 3: Rodar e confirmar que falha**

Run: `php artisan test --filter=PoliticaDeUploadTest`
Expected: FAIL com `Class "App\Support\Arquivos\PoliticaDeUpload" not found`.

- [ ] **Step 4: Criar a política**

```php
<?php

namespace App\Support\Arquivos;

/**
 * O que o CRM aceita como anexo, num lugar só.
 *
 * Lista de PERMITIDOS: um tipo perigoso novo não passa só porque ninguém
 * lembrou de proibi-lo. Fora da lista, de propósito:
 *   - html/htm/svg: servidos do mesmo domínio viram XSS armazenado;
 *   - executáveis e scripts;
 *   - compactados: escondem qualquer um dos anteriores.
 *
 * `mimes` do Laravel compara a extensão ADIVINHADA PELO CONTEÚDO
 * (UploadedFile::guessExtension), não a do nome — um .exe renomeado para
 * .pdf é recusado.
 */
final class PoliticaDeUpload
{
    public const MAX_KB = 10240;

    public const EXTENSOES = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods',
        'txt', 'csv',
    ];

    public static function regras(): array
    {
        return ['required', 'file', 'mimes:'.implode(',', self::EXTENSOES), 'max:'.self::MAX_KB];
    }

    public static function mensagens(string $campo = 'arquivo'): array
    {
        return [
            "{$campo}.required" => 'Selecione um arquivo.',
            "{$campo}.file" => 'Envie um arquivo válido.',
            // Disparada quando o PHP descarta o upload (upload_max_filesize /
            // post_max_size) antes de a validação ver o conteúdo.
            "{$campo}.uploaded" => 'O arquivo passa do limite de upload do servidor. Envie um arquivo menor.',
            "{$campo}.mimes" => 'Tipo de arquivo não permitido. Envie PDF, imagem, documento, planilha, apresentação, TXT ou CSV.',
            "{$campo}.max" => 'O arquivo pode ter no máximo '.intdiv(self::MAX_KB, 1024).' MB.',
        ];
    }
}
```

- [ ] **Step 5: Usar a política nas duas FormRequests**

Em `app/Http/Requests/ArquivoRequest.php`, adicione `use App\Support\Arquivos\PoliticaDeUpload;` e troque `rules()` e `messages()`:

```php
    public function rules(): array
    {
        return [
            'arquivo' => PoliticaDeUpload::regras(),
            'nome' => ['nullable', 'string', 'max:255'],
            'usuario_id' => [
                'required',
                'integer',
                Rule::exists('usuarios', 'id')->where('tenant_id', app(CurrentTenant::class)->id()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            ...PoliticaDeUpload::mensagens(),
            'nome.max' => 'O nome do arquivo pode ter no máximo 255 caracteres.',
            'usuario_id.required' => 'O usuário detentor do arquivo é obrigatório',
            'usuario_id.exists' => 'Usuário inválido.',
        ];
    }
```

Em `app/Http/Requests/ProjetoAnexoRequest.php`, o mesmo, mantendo as mensagens de `usuario_id` que já existem ali:

```php
    public function rules(): array
    {
        return [
            'arquivo' => PoliticaDeUpload::regras(),
            'nome' => ['nullable', 'string', 'max:255'],
            'usuario_id' => [
                'required',
                'integer',
                Rule::exists('projetos', 'id')->where('tenant_id', app(CurrentTenant::class)->id()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            ...PoliticaDeUpload::mensagens(),
            'nome.max' => 'O nome do arquivo pode ter no máximo 255 caracteres.',
            'usuario_id.required' => 'O projeto do anexo é obrigatório.',
            'usuario_id.exists' => 'Projeto inválido.',
        ];
    }
```

- [ ] **Step 6: Documentar o limite do PHP no README**

Na seção de requisitos do `README.md`, logo abaixo da linha de extensões do PHP, adicione:

```markdown
- `php.ini`: `upload_max_filesize = 12M` e `post_max_size = 16M`. O limite do sistema é 10 MB por anexo
  (`App\Support\Arquivos\PoliticaDeUpload::MAX_KB`); com os valores padrão do PHP (2M/8M) o arquivo é
  descartado antes da validação e o usuário recebe "passa do limite de upload do servidor".
```

- [ ] **Step 7: Rodar o teste da tarefa e a suíte inteira**

Run: `php artisan test --filter=PoliticaDeUploadTest && php artisan test`
Expected: PASS nos dois. Se `FormRequestsTest` falhar por causa de `UploadedFile::fake()->create('a.pdf', 10)`, que não informa o mime, confira que o mime adivinhado pelo nome é `application/pdf`. Deve passar sem alteração.

- [ ] **Step 8: Commit** (pular — ver Global Constraints)

```bash
git add app/Support/Arquivos tests/Concerns tests/Feature/Arquivos app/Http/Requests/ArquivoRequest.php app/Http/Requests/ProjetoAnexoRequest.php README.md
git commit -m "fix(P2): politica unica de tipos e tamanho de upload"
```

---

### Task 2: `ArquivoService` grava em disco privado, particionado por tenant, e não deixa órfão (P1, parte 1)

**Causa raiz:** o destino é o disco `public` (`app/Service/ArquivoService.php:8`), o caminho não identifica tenant nem dono, e o controller grava o arquivo antes da linha sem desfazer nada quando a linha falha.

**Files:**
- Create: `app/Services/Arquivos/ArquivoService.php`
- Delete: `app/Service/ArquivoService.php` (e a pasta `app/Service`, que fica vazia)
- Create: `database/migrations/2026_09_28_100001_add_tamanho_e_mime_aos_anexos.php`
- Modify: `app/Http/Controllers/arquivo.php` (`use`, `store`)
- Modify: `app/Http/Controllers/ProjetoController.php` (`use`, `createAnexo`)
- Modify: `app/Models/arquivo.php`, `app/Models/ProjetoAnexo.php` (`$fillable`)
- Modify: `tests/Feature/Autorizacao/FormRequestsTest.php:37` (`Storage::fake('public')` → `Storage::fake('local')`)
- Modify: `tests/Feature/Autorizacao/GuardaDeArrayNoUsuarioIdTest.php` (adicionar `Storage::fake('local')` no `setUp`)
- Test: `tests/Feature/Arquivos/GravacaoDeArquivoTest.php`

**Interfaces:**
- Consumes: `PoliticaDeUpload` (Tarefa 1), `CenarioDeTenant` (Tarefa 1).
- Produces:
  - `ArquivoService::DISCO` = `'local'`
  - `ArquivoService::pastaDoLead(int $tenantId, int $leadId): string`
  - `ArquivoService::pastaDoProjeto(int $tenantId, int $projetoId): string`
  - `ArquivoService::guardarComRegistro(UploadedFile $arquivo, string $pasta, callable $registrar): Model`. O `$registrar` recebe `array{local:string,tamanho:int,mime:?string}` e devolve o model criado.
  - `ArquivoService::remover(?string $caminho): void`
  - `ArquivoService::baixar(string $caminho, ?string $nome): StreamedResponse`
  - Colunas `tamanho` e `mime` em `arquivos` e `projetoAnexos`.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Arquivos;

use App\Models\arquivo;
use App\Models\Projeto;
use App\Models\ProjetoAnexo;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * P1 (gravação): o arquivo vai para o disco PRIVADO, num caminho que diz de
 * que empresa e de que lead/projeto ele é, e nunca fica no disco sem linha no
 * banco.
 */
class GravacaoDeArquivoTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;
    private User $dono;
    private Usuario $lead;
    private Projeto $projeto;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->tenant = $this->novoTenant();
        $this->dono = $this->agente($this->tenant);
        $this->lead = $this->lead($this->tenant, $this->dono);
        $this->projeto = $this->projeto($this->lead);
    }

    public function test_anexo_de_lead_vai_para_o_disco_privado_na_pasta_do_lead(): void
    {
        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'nome' => 'RG',
            'arquivo' => UploadedFile::fake()->create('rg.pdf', 30, 'application/pdf'),
        ])->assertSuccessful();

        $linha = arquivo::firstOrFail();

        $this->assertStringStartsWith("tenants/{$this->tenant->id}/leads/{$this->lead->id}/", $linha->local);
        $this->assertStringEndsWith('.pdf', $linha->local);
        Storage::disk('local')->assertExists($linha->local);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(30 * 1024, (int) $linha->tamanho);
        $this->assertSame('application/pdf', $linha->mime);
    }

    public function test_anexo_de_projeto_vai_para_a_pasta_do_projeto(): void
    {
        $this->actingAs($this->dono)->postJson('/api/projetoAnexo', [
            'usuario_id' => $this->projeto->id,
            'arquivo' => UploadedFile::fake()->create('contrato.pdf', 10, 'application/pdf'),
        ])->assertSuccessful();

        $linha = ProjetoAnexo::firstOrFail();

        $this->assertStringStartsWith("tenants/{$this->tenant->id}/projetos/{$this->projeto->id}/", $linha->local);
        Storage::disk('local')->assertExists($linha->local);
    }

    public function test_extensao_gravada_vem_do_conteudo_e_nao_do_nome(): void
    {
        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'arquivo' => UploadedFile::fake()->create('foto.pdf', 10, 'image/png'),
        ])->assertSuccessful();

        $this->assertStringEndsWith('.png', arquivo::firstOrFail()->local);
    }

    public function test_falha_ao_gravar_a_linha_nao_deixa_arquivo_no_disco(): void
    {
        arquivo::creating(fn () => throw new \RuntimeException('falha simulada'));

        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'arquivo' => UploadedFile::fake()->create('rg.pdf', 10),
        ])->assertServerError();

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_falha_ao_gravar_o_anexo_de_projeto_nao_deixa_arquivo_no_disco(): void
    {
        ProjetoAnexo::creating(fn () => throw new \RuntimeException('falha simulada'));

        $this->actingAs($this->dono)->postJson('/api/projetoAnexo', [
            'usuario_id' => $this->projeto->id,
            'arquivo' => UploadedFile::fake()->create('c.pdf', 10),
        ])->assertServerError();

        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_resposta_do_upload_nao_expoe_o_caminho_interno(): void
    {
        $resposta = $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'arquivo' => UploadedFile::fake()->create('rg.pdf', 10),
        ])->assertSuccessful();

        $this->assertStringNotContainsString('tenants/', $resposta->getContent());
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=GravacaoDeArquivoTest`
Expected: FAIL. O caminho começa com `arquivos/`, o disco `local` está vazio e a coluna `tamanho` não existe.

- [ ] **Step 3: Migration de `tamanho` e `mime`**

`database/migrations/2026_09_28_100001_add_tamanho_e_mime_aos_anexos.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadados que o upload conhece e jogava fora. `Perfil.vue` já exibe
 * `arquivo.tamanho` — até aqui sempre vazio, porque a coluna não existia.
 * Nullable: as linhas antigas só ganham valor quando o comando
 * `arquivos:migrar-para-privado` passar por elas.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['arquivos', 'projetoAnexos'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->unsignedBigInteger('tamanho')->nullable()->after('local');
                $table->string('mime', 100)->nullable()->after('tamanho');
            });
        }
    }

    public function down(): void
    {
        foreach (['arquivos', 'projetoAnexos'] as $tabela) {
            Schema::table($tabela, function (Blueprint $table) {
                $table->dropColumn(['tamanho', 'mime']);
            });
        }
    }
};
```

Adicione `'tamanho'` e `'mime'` ao `$fillable` de `app/Models/arquivo.php` e de `app/Models/ProjetoAnexo.php`.

- [ ] **Step 4: Criar o novo `ArquivoService` e apagar o antigo**

`app/Services/Arquivos/ArquivoService.php`:

```php
<?php

namespace App\Services\Arquivos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Dono único do ciclo de vida de um anexo em disco: gravar, servir, remover.
 *
 * Disco `local` (storage/app/private), nunca `public`: o que está em
 * `public` é servido pelo servidor web em /storage sem passar pelo Laravel,
 * portanto sem login, sem tenant e sem policy.
 *
 * O caminho carrega tenant e dono (`tenants/{t}/leads/{id}/…`). Isso não é
 * controle de acesso — quem controla é a policy na rota de download —, mas
 * torna possível apagar, exportar ou auditar os arquivos de uma empresa ou de
 * um lead sem consultar o banco linha a linha (LGPD).
 */
class ArquivoService
{
    public const DISCO = 'local';

    public static function pastaDoLead(int $tenantId, int $leadId): string
    {
        return "tenants/{$tenantId}/leads/{$leadId}";
    }

    public static function pastaDoProjeto(int $tenantId, int $projetoId): string
    {
        return "tenants/{$tenantId}/projetos/{$projetoId}";
    }

    /**
     * Grava o arquivo e deixa `$registrar` criar a linha. Se a linha falhar, o
     * arquivo sai do disco antes de a exceção seguir — sem órfão.
     *
     * @param  callable(array{local:string,tamanho:int,mime:?string}): Model  $registrar
     */
    public function guardarComRegistro(UploadedFile $arquivo, string $pasta, callable $registrar): Model
    {
        // Extensão pelo conteúdo, não pelo nome que o cliente mandou.
        $nomeInterno = Str::uuid()->toString().'.'.($arquivo->guessExtension() ?? 'bin');
        $caminho = $arquivo->storeAs($pasta, $nomeInterno, self::DISCO);

        if ($caminho === false) {
            throw new RuntimeException('Não foi possível gravar o arquivo.');
        }

        try {
            return $registrar([
                'local' => $caminho,
                'tamanho' => $arquivo->getSize(),
                'mime' => $arquivo->getMimeType(),
            ]);
        } catch (\Throwable $erro) {
            $this->remover($caminho);

            throw $erro;
        }
    }

    public function remover(?string $caminho): void
    {
        if ($caminho) {
            Storage::disk(self::DISCO)->delete($caminho);
        }
    }

    /**
     * Sempre como anexo (nunca inline) e com nosniff: mesmo um arquivo que
     * passou pela PoliticaDeUpload não deve ser interpretado pelo navegador no
     * domínio da aplicação.
     */
    public function baixar(string $caminho, ?string $nome): StreamedResponse
    {
        abort_unless(Storage::disk(self::DISCO)->exists($caminho), 404);

        // Review Focus 5: nome vazio geraria Content-Disposition inválido.
        $nomeDoDownload = filled($nome) ? $nome : basename($caminho);

        return Storage::disk(self::DISCO)->download($caminho, $nomeDoDownload, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
```

Apague o antigo:

```bash
git rm app/Service/ArquivoService.php
rmdir app/Service 2>/dev/null || true
grep -rn "App\\\\Service\\\\ArquivoService" app tests
```
Expected do grep: só `arquivo.php` e `ProjetoController.php`, que o próximo passo corrige.

- [ ] **Step 5: Usar o serviço nos dois controllers**

Em `app/Http/Controllers/arquivo.php`, troque `use App\Service\ArquivoService;` por `use App\Services\Arquivos\ArquivoService;` e o método `store` por:

```php
    public function store(ArquivoRequest $request)
    {
        // O id que vai para o banco é o do model que ArquivoRequest::authorize()
        // já resolveu e autorizou (via integer()), não o texto cru do corpo.
        $lead = Usuario::findOrFail($request->integer('usuario_id'));

        $arquivoSalvo = $this->arquivoService->guardarComRegistro(
            $request->file('arquivo'),
            ArquivoService::pastaDoLead($lead->tenant_id, $lead->id),
            fn (array $dados) => ArquivoModel::create([
                ...$dados,
                'nome' => $request->validated('nome') ?: '',
                'usuario_id' => $lead->id,
            ]),
        );

        return response()->json([
            'success' => true,
            'data' => $arquivoSalvo,
        ]);
    }
```

Em `app/Http/Controllers/ProjetoController.php`, troque o `use` da mesma forma e o `createAnexo` por este. O `try/catch (\Exception)` que devolvia "Erro ao salvar anexo" sai: ele engolia o erro sem registro, e agora a exceção segue para o handler, que loga e responde 500.

```php
    public function createAnexo(ProjetoAnexoRequest $request)
    {
        // Mesmo motivo do irmão arquivo::store(): o id é o que
        // ProjetoAnexoRequest::authorize() já resolveu e autorizou.
        $projeto = Projeto::findOrFail($request->integer('usuario_id'));

        $anexo = $this->arquivoService->guardarComRegistro(
            $request->file('arquivo'),
            ArquivoService::pastaDoProjeto($projeto->tenant_id, $projeto->id),
            fn (array $dados) => ProjetoAnexo::create([
                ...$dados,
                'nome' => $request->validated('nome') ?: '',
                'projeto_id' => $projeto->id,
            ]),
        );

        return response()->json([
            'success' => true,
            'data' => $anexo,
        ], 201);
    }
```

- [ ] **Step 6: Apontar os testes antigos para o disco novo**

- Em `tests/Feature/Autorizacao/FormRequestsTest.php`, no `setUp()`, troque `Storage::fake('public');` por `Storage::fake('local');` e atualize o comentário acima dele para "O ArquivoService grava no disco privado ('local')".
- Em `tests/Feature/Autorizacao/GuardaDeArrayNoUsuarioIdTest.php`, adicione `Storage::fake('local');` como primeira linha do `setUp()` depois de `parent::setUp();`, com o `use Illuminate\Support\Facades\Storage;`. Hoje, se uma daquelas requisições passasse, ela escreveria no disco real.

- [ ] **Step 7: Rodar o teste da tarefa e a suíte inteira**

Run: `php artisan test --filter=GravacaoDeArquivoTest && php artisan test`
Expected: PASS nos dois.

- [ ] **Step 8: Commit** (pular)

```bash
git add -A app/Services/Arquivos app/Service database/migrations/2026_09_28_100001_add_tamanho_e_mime_aos_anexos.php app/Http/Controllers/arquivo.php app/Http/Controllers/ProjetoController.php app/Models/arquivo.php app/Models/ProjetoAnexo.php tests/Feature/Arquivos/GravacaoDeArquivoTest.php tests/Feature/Autorizacao/FormRequestsTest.php tests/Feature/Autorizacao/GuardaDeArrayNoUsuarioIdTest.php
git commit -m "fix(P1): anexos em disco privado particionado por tenant, sem orfao"
```

---

### Task 3: Download autenticado, passando pela policy (P1, parte 2)

**Causa raiz:** não existe rota de download. O front monta `/storage/${local}`, e nenhuma policy é consultada para o conteúdo.

**Files:**
- Create: `app/Http/Controllers/DownloadDeArquivoController.php`
- Modify: `routes/web.php` (duas rotas GET)
- Modify: `app/Policies/ProjetoAnexoPolicy.php` (adicionar `view`)
- Modify: `app/Models/arquivo.php`, `app/Models/ProjetoAnexo.php` (`$hidden`, `$appends`, accessor `url_download`)
- Modify: `resources/js/Pages/Usuario/Perfil.vue` (link na linha 589; URLs relativas nas linhas 122, 194, 214, 239, 284 e 334; barra final na linha 251)
- Modify: `resources/js/Pages/Usuario/ProjetoPanel.vue` (link na linha 670)
- Test: `tests/Feature/Arquivos/DownloadProtegidoTest.php`

**Interfaces:**
- Consumes: `ArquivoService::baixar()` (Tarefa 2).
- Produces:
  - rota `arquivos.download` → `GET /arquivos/{arquivo}/download`
  - rota `projetoAnexos.download` → `GET /projeto-anexos/{projetoAnexo}/download`
  - atributo JSON `url_download` nos dois models
  - `local` deixa de sair no JSON
  - `ProjetoAnexoPolicy::view(User, ProjetoAnexo): Response`

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Arquivos;

use App\Models\arquivo;
use App\Models\ProjetoAnexo;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * P1 (leitura): o conteúdo de um anexo só sai por uma rota que autentica e
 * pergunta à policy — a mesma regra de quem pode ver o lead.
 */
class DownloadProtegidoTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;
    private User $dono;
    private User $intruso;
    private User $gestor;
    private Usuario $lead;
    private arquivo $arquivo;
    private ProjetoAnexo $anexo;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->tenant = $this->novoTenant();
        $this->dono = $this->agente($this->tenant);
        $this->intruso = $this->agente($this->tenant);
        $this->gestor = $this->agente($this->tenant, 'gestor');
        $this->lead = $this->lead($this->tenant, $this->dono);
        $projeto = $this->projeto($this->lead);

        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'nome' => 'rg.pdf',
            'arquivo' => UploadedFile::fake()->create('rg.pdf', 10),
        ])->assertSuccessful();
        $this->arquivo = arquivo::firstOrFail();

        $this->actingAs($this->dono)->postJson('/api/projetoAnexo', [
            'usuario_id' => $projeto->id,
            'nome' => 'contrato.pdf',
            'arquivo' => UploadedFile::fake()->create('contrato.pdf', 10),
        ])->assertSuccessful();
        $this->anexo = ProjetoAnexo::firstOrFail();
    }

    public function test_dono_baixa_como_anexo_e_com_nosniff(): void
    {
        $this->actingAs($this->dono)
            ->get(route('arquivos.download', $this->arquivo))
            ->assertOk()
            ->assertDownload('rg.pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_gestor_baixa_arquivo_da_equipe(): void
    {
        $this->actingAs($this->gestor)->get(route('arquivos.download', $this->arquivo))->assertOk();
    }

    public function test_colega_sem_view_all_recebe_404(): void
    {
        $this->actingAs($this->intruso)->get(route('arquivos.download', $this->arquivo))->assertNotFound();
    }

    public function test_usuario_de_outro_tenant_recebe_404(): void
    {
        $outro = $this->novoTenant();
        $estranho = $this->agente($outro, 'admin');

        $this->actingAs($estranho)->get(route('arquivos.download', $this->arquivo))->assertNotFound();
    }

    public function test_visitante_vai_para_o_login(): void
    {
        auth()->logout();

        $this->get(route('arquivos.download', $this->arquivo))->assertRedirect(route('login'));
    }

    public function test_arquivo_na_lixeira_nao_e_servido(): void
    {
        $this->arquivo->delete();

        $this->actingAs($this->dono)->get("/arquivos/{$this->arquivo->id}/download")->assertNotFound();
    }

    /** Review Focus 5 */
    public function test_nome_vazio_ainda_gera_nome_de_download_valido(): void
    {
        $this->arquivo->update(['nome' => '']);

        $this->actingAs($this->dono)
            ->get(route('arquivos.download', $this->arquivo))
            ->assertOk()
            ->assertDownload(basename($this->arquivo->local));
    }

    public function test_listagem_nao_expoe_caminho_e_traz_url_de_download(): void
    {
        $this->actingAs($this->dono)
            ->getJson("/api/arquivos?user_id={$this->lead->id}")
            ->assertOk()
            ->assertJsonMissingPath('0.local')
            ->assertJsonPath('0.url_download', route('arquivos.download', $this->arquivo));
    }

    public function test_anexo_de_projeto_segue_as_mesmas_regras(): void
    {
        $url = route('projetoAnexos.download', $this->anexo);

        $this->actingAs($this->dono)->get($url)->assertOk()->assertDownload('contrato.pdf');
        $this->actingAs($this->gestor)->get($url)->assertOk();
        $this->actingAs($this->intruso)->get($url)->assertNotFound();

        $this->anexo->delete();
        $this->actingAs($this->dono)->get($url)->assertNotFound();
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=DownloadProtegidoTest`
Expected: FAIL com `Route [arquivos.download] not defined`.

- [ ] **Step 3: `view` em `ProjetoAnexoPolicy`**

Substitua o conteúdo da classe:

```php
class ProjetoAnexoPolicy
{
    public function view(User $user, ProjetoAnexo $anexo): Response
    {
        return $this->doDono($user, $anexo);
    }

    public function delete(User $user, ProjetoAnexo $anexo): Response
    {
        return $this->doDono($user, $anexo);
    }

    private function doDono(User $user, ProjetoAnexo $anexo): Response
    {
        if ($user->can('leads.view-all')) {
            return Response::allow();
        }

        return $anexo->projeto?->usuario?->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
```

(A Tarefa 6 troca o `doDono` de todas as policies por um trait comum; aqui só se adiciona `view`.)

- [ ] **Step 4: Controller de download**

`app/Http/Controllers/DownloadDeArquivoController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\arquivo as ArquivoModel;
use App\Models\ProjetoAnexo;
use App\Services\Arquivos\ArquivoService;

/**
 * A única porta de saída do conteúdo de um anexo.
 *
 * Em routes/web.php e não em api.php: é aberto por um <a href> comum, que
 * carrega a sessão web. O binding usa o TenantScope (arquivo de outro tenant
 * = 404) e exclui a lixeira (soft-deleted = 404); a policy decide o resto,
 * com a mesma regra de quem pode ver o lead.
 */
class DownloadDeArquivoController extends Controller
{
    public function __construct(private readonly ArquivoService $arquivos)
    {
    }

    public function lead(ArquivoModel $arquivo)
    {
        $this->authorize('view', $arquivo);

        return $this->arquivos->baixar($arquivo->local, $arquivo->nome);
    }

    public function projeto(ProjetoAnexo $projetoAnexo)
    {
        $this->authorize('view', $projetoAnexo);

        return $this->arquivos->baixar($projetoAnexo->local, $projetoAnexo->nome);
    }
}
```

- [ ] **Step 5: Rotas**

Em `routes/web.php`, logo depois da rota `leads.show`:

```php
/*
 * Download de anexos. Ver DownloadDeArquivoController: é a única saída do
 * conteúdo, e passa por auth, tenant e policy. O disco `public` não guarda
 * mais anexo nenhum.
 */
Route::middleware(['auth', 'verified', 'tenant'])->group(function () {
    Route::get('/arquivos/{arquivo}/download', [App\Http\Controllers\DownloadDeArquivoController::class, 'lead'])
        ->name('arquivos.download');
    Route::get('/projeto-anexos/{projetoAnexo}/download', [App\Http\Controllers\DownloadDeArquivoController::class, 'projeto'])
        ->name('projetoAnexos.download');
});
```

- [ ] **Step 6: Esconder `local` e expor `url_download`**

Em `app/Models/arquivo.php`, dentro da classe:

```php
    /**
     * O caminho no disco é detalhe interno. O cliente recebe a URL que passa
     * pela policy (DownloadDeArquivoController), nunca o caminho.
     */
    protected $hidden = ['local'];

    protected $appends = ['url_download'];

    public function getUrlDownloadAttribute(): string
    {
        return route('arquivos.download', $this);
    }
```

Em `app/Models/ProjetoAnexo.php`, o mesmo bloco, trocando a rota por `route('projetoAnexos.download', $this)`.

- [ ] **Step 7: Front usa a URL do servidor e URLs absolutas**

Em `resources/js/Pages/Usuario/Perfil.vue`:
- linha 589: `` :href="`/storage/${arquivo.local}`" `` → `:href="arquivo.url_download"`;
- linhas 122, 194, 214, 239, 284 e 334: toda string que começa com `` `api/ `` ou `'api/` passa a começar com `/api/`. A página vive em `/leads/{id}`, então `api/arquivos` virava `/leads/api/arquivos`;
- linha 251: `` `/api/buscarArquivo/` `` → `'/api/buscarArquivo'`. A barra final gera um 301 no Apache, que transforma o POST em GET.

Em `resources/js/Pages/Usuario/ProjetoPanel.vue`, linha 670: `` :href="`/storage/${anexo.local}`" `` → `:href="anexo.url_download"`.

Confira:

```bash
grep -rn "/storage/\|'api/\|\`api/" resources/js
```
Expected: nenhuma ocorrência.

- [ ] **Step 8: Rodar o teste da tarefa, a suíte inteira e o build**

Run: `php artisan test --filter=DownloadProtegidoTest && php artisan test && npm run build`
Expected: PASS e build ok. A rede `test_toda_rota_get_com_model_autoriza_ou_esta_isenta` deve passar sem isenção nova: as duas ações chamam `$this->authorize(`.

- [ ] **Step 9: Commit** (pular)

```bash
git add app/Http/Controllers/DownloadDeArquivoController.php routes/web.php app/Policies/ProjetoAnexoPolicy.php app/Models/arquivo.php app/Models/ProjetoAnexo.php resources/js/Pages/Usuario/Perfil.vue resources/js/Pages/Usuario/ProjetoPanel.vue tests/Feature/Arquivos/DownloadProtegidoTest.php
git commit -m "fix(P1): download de anexo autenticado e autorizado pela policy"
```

---

### Task 4: Apagar o arquivo físico no `forceDelete` e migrar os anexos antigos (P1, parte 3)

**Causa raiz:** nenhum caminho remove o arquivo do disco, e os 19 anexos que já existem continuam no disco público.

**Files:**
- Modify: `app/Observers/ArquivoObserver.php`, `app/Observers/ProjetoAnexoObserver.php` (adicionar `forceDeleted`)
- Create: `app/Console/Commands/MigrarArquivosParaPrivado.php`
- Test: `tests/Feature/Arquivos/CicloDeVidaDoArquivoTest.php`

**Interfaces:**
- Consumes: `ArquivoService::remover()`, `pastaDoLead()`, `pastaDoProjeto()` (Tarefa 2).
- Produces: o comando `arquivos:migrar-para-privado {--dry-run} {--limpar-orfaos}`.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Arquivos;

use App\Models\arquivo;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class CicloDeVidaDoArquivoTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;
    private User $dono;
    private Usuario $lead;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->tenant = $this->novoTenant();
        $this->dono = $this->agente($this->tenant);
        $this->lead = $this->lead($this->tenant, $this->dono);
    }

    private function enviar(): arquivo
    {
        $this->actingAs($this->dono)->postJson('/api/arquivos', [
            'usuario_id' => $this->lead->id,
            'arquivo' => UploadedFile::fake()->create('rg.pdf', 10),
        ])->assertSuccessful();

        return arquivo::latest('id')->firstOrFail();
    }

    public function test_lixeira_mantem_o_arquivo_para_poder_restaurar(): void
    {
        $arquivo = $this->enviar();
        $arquivo->delete();

        Storage::disk('local')->assertExists($arquivo->local);
    }

    public function test_exclusao_definitiva_remove_o_arquivo_do_disco(): void
    {
        $arquivo = $this->enviar();
        $arquivo->forceDelete();

        Storage::disk('local')->assertMissing($arquivo->local);
    }

    /** Linha antiga, do jeito que o sistema gravava até a Tarefa 2. */
    private function anexoLegado(string $local, bool $comArquivo = true): arquivo
    {
        if ($comArquivo) {
            Storage::disk('public')->put($local, 'conteudo-legado');
        }

        return arquivo::create(['nome' => basename($local), 'local' => $local, 'usuario_id' => $this->lead->id]);
    }

    public function test_dry_run_so_relata(): void
    {
        $legado = $this->anexoLegado('arquivos/antigo.pdf');

        $this->artisan('arquivos:migrar-para-privado', ['--dry-run' => true])
            ->expectsOutputToContain('arquivos/antigo.pdf')
            ->assertSuccessful();

        $this->assertSame('arquivos/antigo.pdf', $legado->fresh()->local);
        Storage::disk('public')->assertExists('arquivos/antigo.pdf');
    }

    public function test_migra_para_a_pasta_do_lead_e_preenche_metadados(): void
    {
        $legado = $this->anexoLegado('arquivos/antigo.pdf');

        $this->artisan('arquivos:migrar-para-privado')->assertSuccessful();

        $novo = $legado->fresh();
        $this->assertStringStartsWith("tenants/{$this->tenant->id}/leads/{$this->lead->id}/", $novo->local);
        Storage::disk('local')->assertExists($novo->local);
        Storage::disk('public')->assertMissing('arquivos/antigo.pdf');
        $this->assertSame(strlen('conteudo-legado'), (int) $novo->tamanho);
    }

    public function test_rodar_duas_vezes_nao_muda_nada_na_segunda(): void
    {
        $legado = $this->anexoLegado('arquivos/antigo.pdf');

        $this->artisan('arquivos:migrar-para-privado')->assertSuccessful();
        $depoisDaPrimeira = $legado->fresh()->local;

        $this->artisan('arquivos:migrar-para-privado')->assertSuccessful();
        $this->assertSame($depoisDaPrimeira, $legado->fresh()->local);
    }

    public function test_linha_sem_arquivo_e_relatada_e_nao_alterada(): void
    {
        $semArquivo = $this->anexoLegado('arquivos/sumiu.pdf', comArquivo: false);

        $this->artisan('arquivos:migrar-para-privado')
            ->expectsOutputToContain('arquivos/sumiu.pdf')
            ->assertSuccessful();

        $this->assertSame('arquivos/sumiu.pdf', $semArquivo->fresh()->local);
    }

    public function test_orfao_no_disco_so_e_apagado_com_a_opcao_explicita(): void
    {
        Storage::disk('public')->put('arquivos/orfao.pdf', 'x');

        $this->artisan('arquivos:migrar-para-privado')
            ->expectsOutputToContain('arquivos/orfao.pdf')
            ->assertSuccessful();
        Storage::disk('public')->assertExists('arquivos/orfao.pdf');

        $this->artisan('arquivos:migrar-para-privado', ['--limpar-orfaos' => true])->assertSuccessful();
        Storage::disk('public')->assertMissing('arquivos/orfao.pdf');
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=CicloDeVidaDoArquivoTest`
Expected: FAIL. Com `forceDelete` o arquivo continua no disco, e o comando não existe.

- [ ] **Step 3: `forceDeleted` nos dois observers**

Em `app/Observers/ArquivoObserver.php`, adicione o `use App\Services\Arquivos\ArquivoService;` e o método:

```php
    /**
     * O soft delete mantém o arquivo (restaurável). Só a exclusão definitiva
     * o remove — e é o único ponto do sistema que faz isso.
     */
    public function forceDeleted(arquivo $arquivo): void
    {
        app(ArquivoService::class)->remover($arquivo->local);
    }
```

Em `app/Observers/ProjetoAnexoObserver.php`, o mesmo, com a assinatura `forceDeleted(ProjetoAnexo $anexo)` e `$anexo->local`.

Atenção: no Laravel, `forceDelete()` em model com `SoftDeletes` dispara também `deleted`. O `deleted` dos dois observers registra `arquivo_removido`/`projeto_anexo_removido` no log, o que é aceitável e verdadeiro. Não altere esse comportamento.

- [ ] **Step 4: O comando**

`app/Console/Commands/MigrarArquivosParaPrivado.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\arquivo;
use App\Models\ProjetoAnexo;
use App\Services\Arquivos\ArquivoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Leva os anexos gravados até a correção do P1 (disco `public`, pasta plana
 * `arquivos/`) para o disco privado, na pasta do tenant e do dono.
 *
 * Idempotente: linha cujo `local` já começa com `tenants/` é pulada. Nada é
 * apagado sem confirmação de cópia (tamanhos iguais), e arquivo em disco sem
 * linha no banco só sai com --limpar-orfaos. Roda sem tenant ativo, por isso
 * lê sem global scopes (TenantScope é fail-closed) e inclui a lixeira.
 */
class MigrarArquivosParaPrivado extends Command
{
    protected $signature = 'arquivos:migrar-para-privado
        {--dry-run : Só relata o que faria}
        {--limpar-orfaos : Apaga do disco público os arquivos sem linha no banco}';

    protected $description = 'Move os anexos do disco público para o privado, particionados por tenant';

    public function handle(): int
    {
        $simular = (bool) $this->option('dry-run');
        $publico = Storage::disk('public');
        $privado = Storage::disk(ArquivoService::DISCO);

        $migrados = [];
        $semArquivo = [];
        $referenciados = [];

        $fontes = [
            [arquivo::class, fn ($l) => ArquivoService::pastaDoLead($l->tenant_id, $l->usuario_id)],
            [ProjetoAnexo::class, fn ($l) => ArquivoService::pastaDoProjeto($l->tenant_id, $l->projeto_id)],
        ];

        foreach ($fontes as [$classe, $pasta]) {
            $classe::withoutGlobalScopes()->chunkById(200, function ($linhas) use (
                $simular, $publico, $privado, $pasta, &$migrados, &$semArquivo, &$referenciados
            ) {
                foreach ($linhas as $linha) {
                    $origem = $linha->local;
                    $referenciados[$origem] = true;

                    if (str_starts_with((string) $origem, 'tenants/')) {
                        continue;
                    }

                    if (! $publico->exists($origem)) {
                        $semArquivo[] = [class_basename($linha), $linha->id, $origem];
                        continue;
                    }

                    $destino = $pasta($linha).'/'.basename($origem);
                    $migrados[] = [class_basename($linha), $linha->id, $origem, $destino];

                    if ($simular) {
                        continue;
                    }

                    $privado->writeStream($destino, $publico->readStream($origem));

                    if ($privado->size($destino) !== $publico->size($origem)) {
                        $privado->delete($destino);
                        $this->error("Cópia divergente, mantido no público: {$origem}");
                        continue;
                    }

                    $linha->forceFill([
                        'local' => $destino,
                        'tamanho' => $privado->size($destino),
                        'mime' => $privado->mimeType($destino) ?: null,
                    ])->saveQuietly();

                    $publico->delete($origem);
                }
            });
        }

        $orfaos = collect($publico->allFiles('arquivos'))
            ->reject(fn ($caminho) => isset($referenciados[$caminho]))
            ->values();

        $this->table(['Tipo', 'Id', 'De', 'Para'], $migrados);
        $this->line(($simular ? '[dry-run] ' : '').count($migrados).' arquivo(s) '.($simular ? 'seriam migrados' : 'migrados').'.');

        if ($semArquivo) {
            $this->warn('Linhas cujo arquivo não existe no disco público (não alteradas):');
            $this->table(['Tipo', 'Id', 'Local'], $semArquivo);
        }

        if ($orfaos->isNotEmpty()) {
            $this->warn('Arquivos no disco público sem linha no banco:');
            $orfaos->each(fn ($c) => $this->line("  {$c}"));

            if ($this->option('limpar-orfaos') && ! $simular) {
                $publico->delete($orfaos->all());
                $this->info($orfaos->count().' órfão(s) removido(s).');
            }
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Rodar o teste da tarefa e a suíte inteira**

Run: `php artisan test --filter=CicloDeVidaDoArquivoTest && php artisan test`
Expected: PASS nos dois.

- [ ] **Step 6: Validar nos dados reais (dev) antes de migrar**

```bash
php artisan migrate                                   # só a 2026_09_28_100001 deve rodar
php artisan arquivos:migrar-para-privado --dry-run | tee "$SCRATCH/migracao-arquivos-dry-run.txt"
```
Expected:
- o número de linhas "seriam migrados" mais as "sem arquivo" é igual ao total de linhas de `arquivos` + `projetoAnexos` do relatório da Tarefa 0;
- os órfãos listados são arquivos que ninguém referencia.

**Se algo não bater, pare e mostre o relatório ao usuário.**

```bash
php artisan arquivos:migrar-para-privado | tee "$SCRATCH/migracao-arquivos.txt"
ls storage/app/public/arquivos | wc -l                # só os órfãos, se houver
find storage/app/private/tenants -type f | wc -l      # os migrados
```

Não use `--limpar-orfaos` sem perguntar ao usuário: eles estão no backup da Tarefa 0, mas apagar é decisão dele.

- [ ] **Step 7: Commit** (pular)

```bash
git add app/Observers/ArquivoObserver.php app/Observers/ProjetoAnexoObserver.php app/Console/Commands/MigrarArquivosParaPrivado.php tests/Feature/Arquivos/CicloDeVidaDoArquivoTest.php
git commit -m "fix(P1): remover arquivo fisico no forceDelete e migrar anexos antigos"
```

---

## Fase 2: Exclusões (P4, depois P3)

### Task 5: `SoftDeletes` em lead e projeto, `CASCADE` → `RESTRICT` e o teste que impede a volta (P4 e P3, parte de schema)

**Causa raiz:** `Usuario` e `Projeto` são os únicos agregados principais sem `SoftDeletes`, e todas as chaves filhas deles, mais `usuarios.user_id`, usam `ON DELETE CASCADE`. Um `delete()` apaga a árvore inteira dentro do banco, sem disparar nenhum evento Eloquent: sem activity log, sem remoção de arquivo, sem nada.

**Files:**
- Create: `database/migrations/2026_09_28_110001_soft_deletes_em_usuarios_e_projetos.php`
- Create: `database/migrations/2026_09_28_110002_trocar_cascade_por_restrict.php`
- Modify: `app/Models/Usuario.php`, `app/Models/Projeto.php` (`use SoftDeletes`)
- Modify: `database/migrations/2026_09_20_162300_*.php` (só o docblock: a afirmação de que as tabelas filhas não têm FK para `usuarios` é falsa)
- Test: `tests/Feature/DataIntegrity/SemCascataDestrutivaTest.php`

**Interfaces:**
- Produces:
  - `Usuario` e `Projeto` com `SoftDeletes`: o binding padrão passa a devolver 404 para registro na lixeira, e `->withTrashed()` libera;
  - FK de filhos para `usuarios`/`projetos`/`users` com `RESTRICT`, exceto `tarefa_padroes.user_id`.

- [ ] **Step 1: Confirmar o nome real de cada FK antes de escrever a migration**

Rode a suíte uma vez para o schema de `CRMLeader_test` ficar migrado e depois:

```bash
mysql -u root -p CRMLeader_test -e "
SELECT rc.TABLE_NAME, rc.CONSTRAINT_NAME, k.COLUMN_NAME, rc.REFERENCED_TABLE_NAME, rc.DELETE_RULE
FROM information_schema.REFERENTIAL_CONSTRAINTS rc
JOIN information_schema.KEY_COLUMN_USAGE k
  ON k.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA AND k.CONSTRAINT_NAME = rc.CONSTRAINT_NAME AND k.TABLE_NAME = rc.TABLE_NAME
WHERE rc.CONSTRAINT_SCHEMA = 'CRMLeader_test' AND rc.REFERENCED_TABLE_NAME IN ('users','usuarios','projetos')
ORDER BY rc.TABLE_NAME;"
```

Expected: oito linhas `CASCADE`:
- `anotacaos.usuario_id`, `arquivos.usuario_id`, `projetos.usuario_id`, `tarefas.usuario_id`, `estagio_historicos.usuario_id`
- `projetoAnexos.projeto_id`, `projetoAnotacaos.projeto_id`
- `usuarios.user_id`

Mais `tarefa_padroes.user_id` (`CASCADE`, fica) e `perdas.user_id` (`SET NULL`, fica).

**Se algum nome não seguir o padrão `{tabela_minúscula}_{coluna}_foreign`, use o nome real no `dropForeign('<nome>')` do Step 4 em vez da forma com array.**

- [ ] **Step 2: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\DataIntegrity;

use App\Models\Anotacao;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * P3/P4, prevenção: o banco nunca apaga em cascata o que é dado da empresa.
 *
 * CASCADE numa FK para users/usuarios/projetos significa que uma exclusão —
 * de um agente, de um lead — leva junto, DENTRO do banco, carteira, histórico
 * e anexos, sem disparar um único evento Eloquent. Era o que acontecia. Este
 * teste lê o schema real e falha se uma migration futura reintroduzir isso.
 */
class SemCascataDestrutivaTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    /** Cascata permitida, com o motivo. */
    private const PERMITIDAS = [
        // Modelos de tarefa são pessoais do agente (não da empresa) e não
        // têm filhos; saem junto com ele.
        'tarefa_padroes.user_id',
    ];

    public function test_nenhuma_fk_para_dado_da_empresa_apaga_em_cascata(): void
    {
        $cascatas = collect(DB::select("
            SELECT rc.TABLE_NAME AS tabela, k.COLUMN_NAME AS coluna
            FROM information_schema.REFERENTIAL_CONSTRAINTS rc
            JOIN information_schema.KEY_COLUMN_USAGE k
              ON k.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
             AND k.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
             AND k.TABLE_NAME = rc.TABLE_NAME
            WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
              AND rc.REFERENCED_TABLE_NAME IN ('users', 'usuarios', 'projetos')
              AND rc.DELETE_RULE = 'CASCADE'
        "))
            ->map(fn ($l) => "{$l->tabela}.{$l->coluna}")
            ->reject(fn ($chave) => in_array($chave, self::PERMITIDAS, true))
            ->values()
            ->all();

        $this->assertSame([], $cascatas, 'FKs com ON DELETE CASCADE para dado da empresa: '.implode(', ', $cascatas));
    }

    public function test_exclusao_definitiva_de_lead_com_filhos_e_recusada_pelo_banco(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);
        Anotacao::create(['descricao' => 'Primeiro contato', 'usuario_id' => $lead->id]);

        $this->expectException(QueryException::class);

        $lead->forceDelete();
    }

    public function test_exclusao_definitiva_de_agente_com_leads_e_recusada_pelo_banco(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $this->lead($tenant, $dono);

        $this->expectException(QueryException::class);

        $dono->delete();
    }

    public function test_lead_e_projeto_tem_lixeira(): void
    {
        $tenant = $this->novoTenant();
        $lead = $this->lead($tenant, $this->agente($tenant));
        $projeto = $this->projeto($lead);

        $projeto->delete();
        $lead->delete();

        $this->assertSoftDeleted('usuarios', ['id' => $lead->id]);
        $this->assertSoftDeleted('projetos', ['id' => $projeto->id]);
    }
}
```

- [ ] **Step 3: Rodar e confirmar que falha**

Run: `php artisan test --filter=SemCascataDestrutivaTest`
Expected: FAIL.
- O primeiro teste lista as oito cascatas.
- Os dois de exclusão definitiva não recebem exceção.
- O último falha com `Call to undefined method forceDelete` ou não encontra `deleted_at`.

- [ ] **Step 4: As duas migrations**

`database/migrations/2026_09_28_110001_soft_deletes_em_usuarios_e_projetos.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lixeira para lead e projeto. Eram os dois únicos agregados principais sem
 * SoftDeletes — justamente os que mais têm filhos.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['usuarios', 'projetos'] as $tabela) {
            Schema::table($tabela, fn (Blueprint $table) => $table->softDeletes());
        }
    }

    public function down(): void
    {
        foreach (['usuarios', 'projetos'] as $tabela) {
            Schema::table($tabela, fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
```

`database/migrations/2026_09_28_110002_trocar_cascade_por_restrict.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CASCADE -> RESTRICT em toda FK que aponta para dado da empresa.
 *
 * Com lixeira (110001), a aplicação nunca precisa de exclusão definitiva
 * nessas tabelas. RESTRICT transforma uma exclusão definitiva acidental —
 * um forceDelete, um DELETE manual, um agente removido — em erro alto, em vez
 * de uma limpeza silenciosa de carteira, histórico e anexos.
 *
 * Travado por tests/Feature/DataIntegrity/SemCascataDestrutivaTest.php.
 */
return new class extends Migration
{
    /** [tabela, coluna, tabela referenciada] */
    private const CHAVES = [
        ['anotacaos', 'usuario_id', 'usuarios'],
        ['arquivos', 'usuario_id', 'usuarios'],
        ['projetos', 'usuario_id', 'usuarios'],
        ['tarefas', 'usuario_id', 'usuarios'],
        ['estagio_historicos', 'usuario_id', 'usuarios'],
        ['projetoAnexos', 'projeto_id', 'projetos'],
        ['projetoAnotacaos', 'projeto_id', 'projetos'],
        ['usuarios', 'user_id', 'users'],
    ];

    public function up(): void
    {
        $this->trocar(fn ($fk) => $fk->restrictOnDelete());
    }

    public function down(): void
    {
        $this->trocar(fn ($fk) => $fk->cascadeOnDelete());
    }

    private function trocar(callable $regra): void
    {
        foreach (self::CHAVES as [$tabela, $coluna, $referencia]) {
            Schema::table($tabela, function (Blueprint $table) use ($coluna, $referencia, $regra) {
                $table->dropForeign([$coluna]);
                $regra($table->foreign($coluna)->references('id')->on($referencia));
            });
        }
    }
};
```

- [ ] **Step 5: `SoftDeletes` nos models**

Em `app/Models/Usuario.php`: `use Illuminate\Database\Eloquent\SoftDeletes;` e `use HasFactory, Notifiable, BelongsToTenant, SoftDeletes;`.
Em `app/Models/Projeto.php`: o mesmo.

- [ ] **Step 6: Corrigir o docblock falso**

Em `database/migrations/2026_09_20_162300_*.php`, substitua a frase que afirma que "nenhuma das tabelas filhas de `usuarios` (anotacaos, tarefas, arquivos, projetos) tem FK para ela" por:

```php
 * As tabelas filhas de `usuarios` (anotacaos, tarefas, arquivos, projetos,
 * estagio_historicos) TÊM FK para ela — RESTRICT desde 2026_09_28_110002,
 * CASCADE antes disso. `activity_log.lead_id` fica sem FK de propósito: o log
 * é append-only e sobrevive à linha que descreve.
```

É só comentário. Não altere nenhuma instrução da migration.

- [ ] **Step 7: Rodar o teste da tarefa e a suíte inteira**

Run: `php artisan test --filter=SemCascataDestrutivaTest && php artisan test`
Expected: PASS nos dois. `ProfileTest::test_user_can_delete_their_account` continua verde, porque aquele usuário não tem leads.

- [ ] **Step 8: Commit** (pular)

```bash
git add database/migrations/2026_09_28_1100* database/migrations/2026_09_20_162300_*.php app/Models/Usuario.php app/Models/Projeto.php tests/Feature/DataIntegrity/SemCascataDestrutivaTest.php
git commit -m "fix(P3,P4): lixeira em lead e projeto; FKs RESTRICT no lugar de CASCADE"
```

---

### Task 6: Excluir e restaurar lead e projeto pela API, com policies coerentes com a lixeira (P4)

**Causa raiz:**
- `Userarios::destroy` responde 201 e não registra nada.
- Não existe rota para excluir nem para restaurar projeto: o botão do `ProjetoPanel.vue:222` recebe 405.
- As policies deixam o gestor alcançar filhos de um lead que está na lixeira: `view-all` passa antes de olhar o lead.
- A regra `unique` de e-mail consulta o banco direto e dá "já está sendo utilizado" para um lead que o usuário nem vê mais.

**Files:**
- Create: `app/Policies/Concerns/DonoDoLead.php`
- Create: `app/Rules/EmailDeLeadDisponivel.php`
- Modify:
  - `app/Policies/UsuarioPolicy.php`, `ProjetoPolicy.php`, `AnotacaoPolicy.php`, `ArquivoPolicy.php`, `TarefaPolicy.php`
  - `app/Policies/ProjetoAnotacaoPolicy.php`, `ProjetoAnexoPolicy.php`
  - `app/Http/Controllers/Userarios.php` (`destroy`, novo `restaurar`)
  - `app/Http/Controllers/ProjetoController.php` (novos `destroy` e `restaurar`)
  - `app/Observers/UsuarioObserver.php`, `ProjetoObserver.php` (`deleted`, `restored`)
  - `app/Http/Requests/UsuarioRequest.php` (regra de e-mail)
  - `routes/api.php`
  - `resources/js/Pages/Usuario/ProjetoPanel.vue:224`: o texto do Swal passa de "Esta ação não pode ser desfeita." para "O projeto vai para a lixeira."
- Test: `tests/Feature/Exclusao/LixeiraDeLeadTest.php`, `tests/Feature/Exclusao/LixeiraDeProjetoTest.php`

**Interfaces:**
- Consumes: `SoftDeletes` em `Usuario`/`Projeto` (Tarefa 5), `CenarioDeTenant`.
- Produces:
  - `DELETE /api/usuarios/{usuario}` → 200
  - `PATCH /api/usuarios/{usuario}/restaurar` → 200
  - `DELETE /api/projeto/{projeto}` → 200
  - `PATCH /api/projeto/{projeto}/restaurar` → 200
  - eventos `lead_removido`, `lead_restaurado`, `projeto_removido`, `projeto_restaurado` no activity log
  - trait `DonoDoLead::doDonoDoLead(User $user, ?Usuario $lead): Response`
  - `UsuarioPolicy::restore`, `ProjetoPolicy::restore`

- [ ] **Step 1: Escrever os testes que falham**

`tests/Feature/Exclusao/LixeiraDeLeadTest.php`:

```php
<?php

namespace Tests\Feature\Exclusao;

use App\Models\Anotacao;
use App\Models\MotivoPerda;
use App\Models\Perda;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class LixeiraDeLeadTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;
    private User $dono;
    private User $intruso;
    private User $gestor;
    private Usuario $lead;
    private Anotacao $anotacao;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->novoTenant();
        $this->dono = $this->agente($this->tenant);
        $this->intruso = $this->agente($this->tenant);
        $this->gestor = $this->agente($this->tenant, 'gestor');
        $this->lead = $this->lead($this->tenant, $this->dono, ['email' => 'cliente@exemplo.com']);
        $this->anotacao = Anotacao::create(['descricao' => 'Primeiro contato', 'usuario_id' => $this->lead->id]);
    }

    public function test_excluir_manda_para_a_lixeira_e_responde_200(): void
    {
        $this->actingAs($this->dono)->deleteJson("/api/usuarios/{$this->lead->id}")->assertOk();

        $this->assertSoftDeleted('usuarios', ['id' => $this->lead->id]);
        $this->assertDatabaseHas('anotacaos', ['id' => $this->anotacao->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('activity_log', ['lead_id' => $this->lead->id, 'event' => 'lead_removido']);
    }

    public function test_lead_na_lixeira_some_das_listagens_e_das_metricas(): void
    {
        $antes = $this->actingAs($this->dono)->postJson('/api/metricas')->json('total_leads');

        $this->actingAs($this->dono)->deleteJson("/api/usuarios/{$this->lead->id}")->assertOk();

        $this->assertSame($antes - 1, $this->actingAs($this->dono)->postJson('/api/metricas')->json('total_leads'));
        $this->assertNotContains($this->lead->id, collect($this->actingAs($this->dono)->postJson('/api/pegarUsuarios')->json())->pluck('id'));
        $this->assertNotContains($this->lead->id, collect($this->actingAs($this->dono)->postJson('/api/kanban')->json('leads'))->pluck('id'));
    }

    /** Review Focus 2 */
    public function test_link_salvo_para_lead_na_lixeira_da_404_limpo(): void
    {
        $this->lead->delete();

        $this->actingAs($this->gestor)->get("/leads/{$this->lead->id}")->assertNotFound();
        $this->actingAs($this->gestor)->getJson("/api/usuarioPerfil/{$this->lead->id}")->assertNotFound();
        $this->actingAs($this->gestor)->putJson("/api/anotacao/{$this->anotacao->id}", ['descricao' => 'x'])->assertNotFound();
    }

    public function test_restaurar_traz_o_lead_de_volta_com_o_historico(): void
    {
        $this->lead->delete();

        $this->actingAs($this->dono)->patchJson("/api/usuarios/{$this->lead->id}/restaurar")->assertOk();

        $this->assertNotSoftDeleted('usuarios', ['id' => $this->lead->id]);
        $this->actingAs($this->dono)->getJson("/api/anotacao/{$this->lead->id}")
            ->assertOk()->assertJsonPath('0.id', $this->anotacao->id);
        $this->assertDatabaseHas('activity_log', ['lead_id' => $this->lead->id, 'event' => 'lead_restaurado']);
    }

    public function test_colega_nao_exclui_nem_restaura(): void
    {
        $this->actingAs($this->intruso)->deleteJson("/api/usuarios/{$this->lead->id}")->assertNotFound();
        $this->assertNotSoftDeleted('usuarios', ['id' => $this->lead->id]);

        $this->lead->delete();
        $this->actingAs($this->intruso)->patchJson("/api/usuarios/{$this->lead->id}/restaurar")->assertNotFound();
        $this->assertSoftDeleted('usuarios', ['id' => $this->lead->id]);
    }

    public function test_gestor_restaura_lead_da_equipe(): void
    {
        $this->lead->delete();

        $this->actingAs($this->gestor)->patchJson("/api/usuarios/{$this->lead->id}/restaurar")->assertOk();
    }

    public function test_email_de_lead_na_lixeira_explica_que_da_para_restaurar(): void
    {
        $this->lead->delete();

        $this->actingAs($this->dono)->postJson('/api/usuarios', [
            'nome' => 'Cliente de novo',
            'email' => 'cliente@exemplo.com',
            'telefone' => '11999998888',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'lead excluído']);
    }

    public function test_email_de_lead_ativo_continua_com_a_mensagem_de_sempre(): void
    {
        $this->actingAs($this->dono)->postJson('/api/usuarios', [
            'nome' => 'Cliente repetido',
            'email' => 'cliente@exemplo.com',
            'telefone' => '11999998888',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'já está sendo utilizado']);
    }

    public function test_editar_o_proprio_lead_sem_trocar_email_continua_valendo(): void
    {
        $this->actingAs($this->dono)->putJson("/api/usuarios/{$this->lead->id}", [
            'nome' => 'Cliente renomeado',
            'email' => 'cliente@exemplo.com',
            'telefone' => '11999998888',
        ])->assertOk();
    }

    /** Perda é fato histórico: continua no relatório depois que o lead vai para a lixeira. */
    public function test_perdas_do_lead_na_lixeira_continuam_no_relatorio(): void
    {
        $motivo = MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->lead->perdas()->save(new Perda(['motivo_perda_id' => $motivo->id, 'user_id' => $this->dono->id]));

        $antes = $this->actingAs($this->gestor)->getJson('/api/relatorios/perdas')->json('total');
        $this->lead->delete();

        $this->assertSame($antes, $this->actingAs($this->gestor)->getJson('/api/relatorios/perdas')->json('total'));
    }
}
```

`tests/Feature/Exclusao/LixeiraDeProjetoTest.php`:

```php
<?php

namespace Tests\Feature\Exclusao;

use App\Models\Projeto;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class LixeiraDeProjetoTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private User $dono;
    private User $intruso;
    private User $gestor;
    private Usuario $lead;
    private Projeto $projeto;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->novoTenant();
        $this->dono = $this->agente($tenant);
        $this->intruso = $this->agente($tenant);
        $this->gestor = $this->agente($tenant, 'gestor');
        $this->lead = $this->lead($tenant, $this->dono);
        $this->projeto = $this->projeto($this->lead, ['preco' => 1500]);
    }

    /** O botão "excluir projeto" do ProjetoPanel recebia 405: a rota não existia. */
    public function test_excluir_projeto_responde_200_e_vai_para_a_lixeira(): void
    {
        $this->actingAs($this->dono)->deleteJson("/api/projeto/{$this->projeto->id}")->assertOk();

        $this->assertSoftDeleted('projetos', ['id' => $this->projeto->id]);
        $this->assertDatabaseHas('activity_log', ['lead_id' => $this->lead->id, 'event' => 'projeto_removido']);
        $this->actingAs($this->dono)->getJson("/api/projeto/{$this->projeto->id}")->assertNotFound();
    }

    public function test_projeto_na_lixeira_sai_da_listagem_e_do_valor_do_kanban(): void
    {
        $this->projeto->delete();

        $this->assertSame([], $this->actingAs($this->dono)->postJson('/api/projetos', ['usuario_id' => $this->lead->id])->json());

        $card = collect($this->actingAs($this->dono)->postJson('/api/kanban')->json('leads'))->firstWhere('id', $this->lead->id);
        $this->assertEquals(0, $card['valor_projetos'] ?? 0);
    }

    public function test_restaurar_projeto(): void
    {
        $this->projeto->delete();

        $this->actingAs($this->dono)->patchJson("/api/projeto/{$this->projeto->id}/restaurar")->assertOk();

        $this->assertNotSoftDeleted('projetos', ['id' => $this->projeto->id]);
        $this->assertDatabaseHas('activity_log', ['lead_id' => $this->lead->id, 'event' => 'projeto_restaurado']);
    }

    public function test_colega_nao_exclui_nem_restaura_projeto(): void
    {
        $this->actingAs($this->intruso)->deleteJson("/api/projeto/{$this->projeto->id}")->assertNotFound();

        $this->projeto->delete();
        $this->actingAs($this->intruso)->patchJson("/api/projeto/{$this->projeto->id}/restaurar")->assertNotFound();
    }

    /** Review Focus 2: nem o gestor alcança o projeto de um lead que está na lixeira. */
    public function test_projeto_de_lead_na_lixeira_da_404_ate_para_o_gestor(): void
    {
        $this->lead->delete();

        $this->actingAs($this->gestor)->getJson("/api/projeto/{$this->projeto->id}")->assertNotFound();
        $this->actingAs($this->gestor)->getJson("/api/projetoAnotacao/{$this->projeto->id}")->assertNotFound();
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter='LixeiraDeLeadTest|LixeiraDeProjetoTest'`
Expected: FAIL:
- `destroy` responde 201;
- a rota `restaurar` e o `DELETE /api/projeto` não existem;
- falta a atividade `lead_removido`;
- o gestor alcança filhos de lead na lixeira;
- a mensagem de e-mail é a genérica.

- [ ] **Step 3: Trait comum das policies**

`app/Policies/Concerns/DonoDoLead.php`:

```php
<?php

namespace App\Policies\Concerns;

use App\Models\User;
use App\Models\Usuario;
use Illuminate\Auth\Access\Response;

/**
 * A regra das oito policies, num lugar só: quem tem `leads.view-all` passa
 * dentro do tenant; quem não tem, passa só no que é seu.
 *
 * Com uma condição antes, que é o motivo de este trait existir: sem lead não
 * há acesso — nem para quem tem view-all. `$filho->usuario` é null quando o
 * lead está na lixeira (SoftDeletes esconde a relação), e a ordem antiga
 * (view-all primeiro) deixava o gestor editar anotação, tarefa e projeto de um
 * lead excluído, que ele já não enxerga em lugar nenhum.
 */
trait DonoDoLead
{
    protected function doDonoDoLead(User $user, ?Usuario $lead): Response
    {
        if ($lead === null) {
            return Response::denyAsNotFound();
        }

        return $user->can('leads.view-all') || $lead->user_id === $user->id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
```

- [ ] **Step 4: As sete policies passam a usar o trait**

Em cada uma, adicione `use App\Policies\Concerns\DonoDoLead;`, `use DonoDoLead;` dentro da classe e troque o corpo do `doDono` privado. Os métodos públicos (`view`/`update`/`delete`) continuam chamando `doDono`.

| Policy | Novo corpo de `doDono` |
|---|---|
| `UsuarioPolicy` | `return $this->doDonoDoLead($user, $usuario);` |
| `ProjetoPolicy` | `return $this->doDonoDoLead($user, $projeto->usuario);` |
| `AnotacaoPolicy` | `return $this->doDonoDoLead($user, $anotacao->usuario);` |
| `ArquivoPolicy` | `return $this->doDonoDoLead($user, $arquivo->usuario);` |
| `TarefaPolicy` | `return $this->doDonoDoLead($user, $tarefa->lead);` |
| `ProjetoAnotacaoPolicy` | `return $this->doDonoDoLead($user, $anotacao->projeto?->usuario);` |
| `ProjetoAnexoPolicy` | `return $this->doDonoDoLead($user, $anexo->projeto?->usuario);` |

Adicione `restore` em `UsuarioPolicy` e `ProjetoPolicy`:

```php
    public function restore(User $user, Usuario $usuario): Response
    {
        return $this->doDono($user, $usuario);
    }
```

```php
    public function restore(User $user, Projeto $projeto): Response
    {
        return $this->doDono($user, $projeto);
    }
```

No `restore` de lead, `$usuario` é o próprio lead na lixeira (não é null), então passa quem é dono ou tem view-all. No `restore` de projeto, `$projeto->usuario` é null se o lead também estiver na lixeira: primeiro se restaura o lead.

- [ ] **Step 5: Controllers e rotas**

Em `app/Http/Controllers/Userarios.php`, substitua `destroy` e adicione `restaurar`:

```php
    public function destroy(Request $request, Usuario $usuario)
    {
        $this->authorize('delete', $usuario);

        // Soft delete: vai para a lixeira. Filhos, perdas e histórico ficam.
        $usuario->delete();

        return response()->json(['message' => 'Lead movido para a lixeira']);
    }

    public function restaurar(Usuario $usuario)
    {
        $this->authorize('restore', $usuario);

        $usuario->restore();

        return response()->json(['message' => 'Lead restaurado']);
    }
```

Em `app/Http/Controllers/ProjetoController.php`, adicione:

```php
    public function destroy(Projeto $projeto)
    {
        $this->authorize('delete', $projeto);

        $projeto->delete();

        return response()->json(['message' => 'Projeto movido para a lixeira']);
    }

    public function restaurar(Projeto $projeto)
    {
        $this->authorize('restore', $projeto);

        $projeto->restore();

        return response()->json(['message' => 'Projeto restaurado']);
    }
```

Em `routes/api.php`, no bloco de leads, logo abaixo de `Route::delete('/usuarios/{usuario}', ...)`:

```php
    // withTrashed: sem ele o binding esconde justamente o lead que se quer
    // restaurar. A policy (restore) é quem decide.
    Route::patch('/usuarios/{usuario}/restaurar', [Userarios::class, 'restaurar'])->withTrashed();
```

No bloco de projetos, logo abaixo de `Route::put('/projeto/{projeto}', ...)`:

```php
    Route::delete('/projeto/{projeto}', [ProjetoController::class, 'destroy']);
    Route::patch('/projeto/{projeto}/restaurar', [ProjetoController::class, 'restaurar'])->withTrashed();
```

- [ ] **Step 6: Observers registram saída e volta da lixeira**

Em `app/Observers/UsuarioObserver.php`, adicione:

```php
    public function deleted(Usuario $usuario): void
    {
        // forceDelete também dispara `deleted`; com RESTRICT ele só passa em
        // lead sem filhos, e o log não deve chamá-lo de "lixeira".
        if ($usuario->isForceDeleting()) {
            return;
        }

        LeadActivity::registrar($usuario, $usuario->id, 'lead_removido', 'Lead movido para a lixeira');
    }

    public function restored(Usuario $usuario): void
    {
        LeadActivity::registrar($usuario, $usuario->id, 'lead_restaurado', 'Lead restaurado da lixeira');
    }
```

Em `app/Observers/ProjetoObserver.php`, adicione:

```php
    public function deleted(Projeto $projeto): void
    {
        if ($projeto->isForceDeleting()) {
            return;
        }

        LeadActivity::registrar($projeto, $projeto->usuario_id, 'projeto_removido', 'Projeto movido para a lixeira', [
            'nome' => $projeto->nome,
        ]);
    }

    public function restored(Projeto $projeto): void
    {
        LeadActivity::registrar($projeto, $projeto->usuario_id, 'projeto_restaurado', 'Projeto restaurado da lixeira', [
            'nome' => $projeto->nome,
        ]);
    }
```

- [ ] **Step 7: Regra de e-mail que conhece a lixeira**

`app/Rules/EmailDeLeadDisponivel.php`:

```php
<?php

namespace App\Rules;

use App\Models\Usuario;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * E-mail único por tenant, contando a lixeira.
 *
 * O índice único (tenant_id, email) continua valendo para o lead excluído —
 * e deve: restaurar não pode colidir. O que muda é a mensagem. A regra
 * `unique:` padrão dizia "já está sendo utilizado" para um lead que o usuário
 * não enxerga mais em lugar nenhum.
 *
 * Passa pelo Eloquent (TenantScope), então só olha o tenant corrente.
 */
class EmailDeLeadDisponivel implements ValidationRule
{
    public function __construct(private readonly ?int $ignorarLeadId = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $existente = Usuario::withTrashed()
            ->where('email', $value)
            ->when($this->ignorarLeadId, fn ($q) => $q->whereKeyNot($this->ignorarLeadId))
            ->first(['id', 'deleted_at']);

        if ($existente === null) {
            return;
        }

        $fail($existente->trashed()
            ? 'Existe um lead excluído com este email. Restaure-o em vez de cadastrar de novo.'
            : 'Este email já está sendo utilizado.');
    }
}
```

Em `app/Http/Requests/UsuarioRequest.php`:
- em `storeRules()`: `'email' => ['required', 'email', new EmailDeLeadDisponivel()],`
- em `updateRules()`: `'email' => ['required', 'email', new EmailDeLeadDisponivel($this->route('usuario')?->id)],`, mantendo o comentário sobre o model vir do binding;
- remova o método `emailUnicoNoTenant()`, o `use Illuminate\Validation\Rules\Unique;` e a mensagem `'email.unique'`;
- adicione `use App\Rules\EmailDeLeadDisponivel;`.

- [ ] **Step 8: Rodar os testes da tarefa e a suíte inteira**

Run: `php artisan test --filter='LixeiraDeLeadTest|LixeiraDeProjetoTest' && php artisan test && npm run build`
Expected: PASS.
- `TodaRotaDeEscritaAutorizaTest` aceita as quatro rotas sem isenção, porque todas têm `$this->authorize(`.
- `LeadEmailUniquenessTest` continua verde, já que a mensagem para lead ativo é a mesma de antes.
- Se `GestorAlcancaEquipeTest` ou `DoisSaltosPolicyTest` falharem, confira que o cenário deles não depende de um lead nulo. Não deveria.

- [ ] **Step 9: Commit** (pular)

```bash
git add app/Policies app/Rules/EmailDeLeadDisponivel.php app/Http/Controllers/Userarios.php app/Http/Controllers/ProjetoController.php app/Observers/UsuarioObserver.php app/Observers/ProjetoObserver.php app/Http/Requests/UsuarioRequest.php routes/api.php resources/js/Pages/Usuario/ProjetoPanel.vue tests/Feature/Exclusao
git commit -m "fix(P4): lixeira e restauracao de lead e projeto; policies respeitam a lixeira"
```

---

### Task 7: `ExclusaoDeConta`, a saída do agente sem levar a carteira (P3)

**Causa raiz:** `ProfileController::destroy` é o código padrão do Breeze, que parte do princípio de que os dados são do próprio usuário. Aqui os dados são da empresa. Depois da Tarefa 5 o banco recusa a exclusão com erro 500. Falta a regra de negócio que explica o motivo e ainda protege o último admin.

**Files:**
- Create: `app/Services/Contas/ExclusaoDeConta.php`
- Modify: `app/Http/Controllers/ProfileController.php` (`edit`, `destroy`)
- Modify: `resources/js/Pages/Profile/Edit.vue` (prop `impedimentosDeExclusao`)
- Modify: `resources/js/Pages/Profile/Partials/DeleteUserForm.vue` (texto, prop, erro `conta`)
- Modify: `tests/Feature/Autorizacao/TodaRotaDeEscritaAutorizaTest.php` (comentário da isenção `'DELETE profile'`)
- Test: `tests/Feature/Contas/ExclusaoDeContaTest.php`

**Interfaces:**
- Consumes: RESTRICT em `usuarios.user_id` e `Usuario::withTrashed()` (Tarefa 5).
- Produces:
  - `ExclusaoDeConta::impedimentos(User $user): list<string>`
  - `ExclusaoDeConta::excluir(User $user): void`. Lança `ValidationException` com a chave `conta` quando há impedimento.
  - Prop Inertia `impedimentosDeExclusao` em `Profile/Edit`.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Contas;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * P3: excluir a própria conta não pode apagar a carteira da empresa nem
 * deixá-la sem admin.
 */
class ExclusaoDeContaTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->novoTenant();
        // Um admin fixo, para que "último admin" só apareça onde o teste quer.
        $this->agente($this->tenant, 'admin');
    }

    private function excluir($user)
    {
        return $this->actingAs($user)->from('/profile')->delete('/profile', ['password' => 'password']);
    }

    public function test_vendedor_com_leads_nao_exclui_e_os_leads_ficam(): void
    {
        $vendedor = $this->agente($this->tenant);
        $lead = $this->lead($this->tenant, $vendedor);

        $this->excluir($vendedor)->assertSessionHasErrors('conta')->assertRedirect('/profile');

        $this->assertNotNull($vendedor->fresh());
        $this->assertDatabaseHas('usuarios', ['id' => $lead->id, 'deleted_at' => null]);
        $this->assertAuthenticatedAs($vendedor);
    }

    public function test_leads_na_lixeira_tambem_impedem(): void
    {
        $vendedor = $this->agente($this->tenant);
        $this->lead($this->tenant, $vendedor)->delete();

        $this->excluir($vendedor)->assertSessionHasErrors('conta');
        $this->assertNotNull($vendedor->fresh());
    }

    public function test_ultimo_admin_nao_exclui(): void
    {
        $outro = $this->novoTenant();
        $unicoAdmin = $this->agente($outro, 'admin');

        $this->excluir($unicoAdmin)->assertSessionHasErrors('conta');
        $this->assertNotNull($unicoAdmin->fresh());
    }

    public function test_admin_com_outro_admin_e_sem_leads_exclui(): void
    {
        $segundoAdmin = $this->agente($this->tenant, 'admin');

        $this->excluir($segundoAdmin)->assertSessionHasNoErrors()->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($segundoAdmin->fresh());
    }

    public function test_vendedor_sem_leads_exclui(): void
    {
        $vendedor = $this->agente($this->tenant);

        $this->excluir($vendedor)->assertSessionHasNoErrors()->assertRedirect('/');
        $this->assertNull($vendedor->fresh());
    }

    public function test_senha_errada_continua_sendo_checada_antes(): void
    {
        $vendedor = $this->agente($this->tenant);

        $this->actingAs($vendedor)->from('/profile')->delete('/profile', ['password' => 'errada'])
            ->assertSessionHasErrors('password')
            ->assertSessionDoesntHaveErrors('conta');
    }

    public function test_tela_de_perfil_ja_informa_o_impedimento(): void
    {
        $vendedor = $this->agente($this->tenant);
        $this->lead($this->tenant, $vendedor);

        $this->actingAs($vendedor)->get('/profile')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Profile/Edit')
                ->has('impedimentosDeExclusao', 1));
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=ExclusaoDeContaTest`
Expected: FAIL. O vendedor com leads leva 500 (`QueryException` do RESTRICT) em vez de erro de validação, o último admin se exclui e a prop não existe.

- [ ] **Step 3: O serviço**

`app/Services/Contas/ExclusaoDeConta.php`:

```php
<?php

namespace App\Services\Contas;

use App\Models\User;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Quando um agente pode deixar o sistema.
 *
 * O Breeze trata a conta como dona dos próprios dados. Aqui a carteira é da
 * EMPRESA: sair não pode levá-la (o banco já recusa — RESTRICT em
 * usuarios.user_id), e o tenant não pode ficar sem ninguém que o administre.
 *
 * Enquanto a transferência de leads (L2) não existir, quem tem leads não sai.
 * É o comportamento seguro; L2 troca "peça a um admin" por um botão.
 */
class ExclusaoDeConta
{
    /** @return list<string> */
    public function impedimentos(User $user): array
    {
        $impedimentos = [];

        // withTrashed: o RESTRICT também conta os leads da lixeira.
        $leads = Usuario::withTrashed()->where('user_id', $user->id)->count();
        if ($leads > 0) {
            $impedimentos[] = $leads === 1
                ? 'Você é dono de 1 lead (contando a lixeira). Peça a um admin para transferi-lo antes de excluir a conta.'
                : "Você é dono de {$leads} leads (contando a lixeira). Peça a um admin para transferi-los antes de excluir a conta.";
        }

        if ($this->ehUltimoAdmin($user)) {
            $impedimentos[] = 'Você é o único admin da empresa. Outra pessoa precisa ser admin antes de você excluir a conta.';
        }

        return $impedimentos;
    }

    public function excluir(User $user): void
    {
        $impedimentos = $this->impedimentos($user);

        if ($impedimentos !== []) {
            throw ValidationException::withMessages(['conta' => $impedimentos]);
        }

        DB::transaction(fn () => $user->delete());
    }

    /**
     * Papéis são por tenant (spatie em modo teams). O team corrente é o do
     * usuário — IdentifyTenant o define em toda requisição autenticada.
     */
    private function ehUltimoAdmin(User $user): bool
    {
        if (! $user->hasRole('admin')) {
            return false;
        }

        return ! User::role('admin')
            ->where('tenant_id', $user->tenant_id)
            ->whereKeyNot($user->id)
            ->exists();
    }
}
```

- [ ] **Step 4: `ProfileController` delega ao serviço**

Adicione `use App\Services\Contas\ExclusaoDeConta;` e troque `edit` e `destroy`:

```php
    public function edit(Request $request, ExclusaoDeConta $exclusao): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'impedimentosDeExclusao' => $exclusao->impedimentos($request->user()),
        ]);
    }

    /**
     * Delete the user's account.
     *
     * A senha é conferida primeiro (um impedimento não deve ser revelado a
     * quem só está com a sessão aberta de outra pessoa); depois a regra de
     * negócio. Só então a sessão é encerrada.
     */
    public function destroy(Request $request, ExclusaoDeConta $exclusao): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        $exclusao->excluir($user);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
```

- [ ] **Step 5: Front mostra o impedimento antes e depois**

Em `resources/js/Pages/Profile/Edit.vue`, adicione a prop e repasse:

```js
defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
    impedimentosDeExclusao: { type: Array, default: () => [] },
});
```

```vue
<DeleteUserForm :impedimentos="impedimentosDeExclusao" />
```

Em `resources/js/Pages/Profile/Partials/DeleteUserForm.vue`:
- no `<script setup>`, adicione `const props = defineProps({ impedimentos: { type: Array, default: () => [] } });`;
- troque o parágrafo `.pf-card-desc` do card por:

```vue
        <p class="pf-card-desc">
            Excluir a conta encerra o seu acesso. Os leads são da empresa e não saem com você:
            enquanto você for dono de algum, ou for o único admin, a conta não pode ser excluída.
        </p>

        <ul v-if="props.impedimentos.length" class="pf-aviso" style="margin-top: 0.9rem">
            <li v-for="motivo in props.impedimentos" :key="motivo">{{ motivo }}</li>
        </ul>
```

- no botão que abre o diálogo, `:disabled="props.impedimentos.length > 0"`;
- dentro do diálogo, troque o parágrafo por "Seu acesso será encerrado. Digite sua senha para confirmar." e, logo abaixo do `<span v-if="form.errors.password">`, adicione:

```vue
                        <span v-if="form.errors.conta" class="pf-erro">
                            {{ form.errors.conta }}
                        </span>
```

- [ ] **Step 6: Atualizar o motivo da isenção**

Em `tests/Feature/Autorizacao/TodaRotaDeEscritaAutorizaTest.php`, troque o comentário da linha `'DELETE profile'` por:

```php
        'DELETE profile',  // ProfileController::destroy -> ExclusaoDeConta::excluir($request->user()): só o próprio usuário, com impedimentos de carteira e último admin
```

- [ ] **Step 7: Rodar o teste da tarefa, a suíte inteira e o build**

Run: `php artisan test --filter='ExclusaoDeContaTest|ProfileTest' && php artisan test && npm run build`
Expected: PASS. `ProfileTest::test_user_can_delete_their_account` continua verde: o usuário da factory não tem leads nem papel de admin.

- [ ] **Step 8: Commit** (pular)

```bash
git add app/Services/Contas app/Http/Controllers/ProfileController.php resources/js/Pages/Profile tests/Feature/Contas tests/Feature/Autorizacao/TodaRotaDeEscritaAutorizaTest.php
git commit -m "fix(P3): excluir conta nao leva a carteira nem deixa o tenant sem admin"
```

---

## Fase 3: Tipos de coluna (P5, P6)

### Task 8: `Telefone` e `TelefoneE164`, uma só definição de telefone válido (P5, parte 1)

**Causa raiz:** o telefone foi modelado como número (`bigInteger`), e a normalização mora no front. Três telas fazem isso de três jeitos, e o quick-add do Kanban nem faz. Esta tarefa cria a definição única. A Tarefa 9 aplica essa definição ao schema, aos dados e às telas.

**Files:**
- Create: `app/Support/Telefone.php`
- Create: `app/Rules/TelefoneE164.php`
- Test: `tests/Unit/TelefoneTest.php`

**Interfaces:**
- Produces:
  - `Telefone::normalizar(?string $valor): ?string`: `null` para vazio; E.164 quando reconhece; o texto limpo sem `+` quando não reconhece (para a regra recusar).
  - `Telefone::formatar(?string $e164): string`
  - `TelefoneE164::valido(?string $valor): bool`, além da regra de validação `new TelefoneE164()`.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Unit;

use App\Rules\TelefoneE164;
use App\Support\Telefone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TelefoneTest extends TestCase
{
    public static function entradas(): array
    {
        return [
            'celular com máscara' => ['(11) 99999-8888', '+5511999998888'],
            'celular só dígitos' => ['11999998888', '+5511999998888'],
            'fixo com máscara' => ['(11) 3333-4444', '+551133334444'],
            'com zero de tronco' => ['011999998888', '+5511999998888'],
            'com 55 sem mais' => ['5511999998888', '+5511999998888'],
            'já em E.164 com espaços' => ['+55 11 99999-8888', '+5511999998888'],
            'internacional' => ['+1 (415) 555-2671', '+14155552671'],
            'curto demais' => ['123', '123'],
            'sem dígitos' => ['abc', 'abc'],
            'vazio' => ['', null],
            'só espaços' => ['   ', null],
            'nulo' => [null, null],
        ];
    }

    #[DataProvider('entradas')]
    public function test_normalizar(?string $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, Telefone::normalizar($entrada));
    }

    public function test_a_regra_aceita_so_e164(): void
    {
        $this->assertTrue(TelefoneE164::valido('+5511999998888'));
        $this->assertTrue(TelefoneE164::valido('+14155552671'));
        $this->assertFalse(TelefoneE164::valido('123'));
        $this->assertFalse(TelefoneE164::valido('11999998888'));
        $this->assertFalse(TelefoneE164::valido('+0011999998888'));
        $this->assertFalse(TelefoneE164::valido(null));
    }

    public function test_formatar(): void
    {
        $this->assertSame('(11) 99999-8888', Telefone::formatar('+5511999998888'));
        $this->assertSame('(11) 3333-4444', Telefone::formatar('+551133334444'));
        $this->assertSame('+14155552671', Telefone::formatar('+14155552671'));
        $this->assertSame('', Telefone::formatar(null));
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=TelefoneTest`
Expected: FAIL com `Class "App\Support\Telefone" not found`.

- [ ] **Step 3: Implementar**

`app/Support/Telefone.php`:

```php
<?php

namespace App\Support;

/**
 * Telefone é texto, não número: tem "+" de país e pode começar com zero.
 *
 * Forma canônica é E.164 (`+5511999998888`), a mesma que WhatsApp e
 * operadoras usam. Sem código de país, assume Brasil — é o público do CRM.
 *
 * `normalizar` não valida: o que ela não reconhece volta como veio (limpo,
 * sem "+"), e quem recusa é a regra TelefoneE164. Assim a mensagem de erro
 * fala do número que o usuário digitou, e nada é descartado em silêncio.
 */
final class Telefone
{
    public static function normalizar(?string $valor): ?string
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        $digitos = preg_replace('/\D/', '', $texto);

        if ($digitos === '') {
            return $texto;
        }

        if (str_starts_with($texto, '+')) {
            return '+'.$digitos;
        }

        // Zero de tronco ("0 11 …") não faz parte do número.
        $digitos = ltrim($digitos, '0');
        $tamanho = strlen($digitos);

        if ($tamanho === 10 || $tamanho === 11) {
            return '+55'.$digitos;
        }

        if (($tamanho === 12 || $tamanho === 13) && str_starts_with($digitos, '55')) {
            return '+'.$digitos;
        }

        return $digitos;
    }

    public static function formatar(?string $e164): string
    {
        if ($e164 === null || $e164 === '') {
            return '';
        }

        if (! preg_match('/^\+55(\d{2})(\d{4,5})(\d{4})$/', $e164, $partes)) {
            return $e164;
        }

        return "({$partes[1]}) {$partes[2]}-{$partes[3]}";
    }
}
```

`app/Rules/TelefoneE164.php`:

```php
<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Telefone em E.164: "+", código do país sem zero, 10 a 15 dígitos no total. */
class TelefoneE164 implements ValidationRule
{
    private const PADRAO = '/^\+[1-9]\d{9,14}$/';

    public static function valido(?string $valor): bool
    {
        return $valor !== null && preg_match(self::PADRAO, $valor) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::valido($value)) {
            $fail('Informe um telefone válido com DDD, por exemplo (11) 99999-8888.');
        }
    }
}
```

- [ ] **Step 4: Rodar o teste da tarefa e a suíte inteira**

Run: `php artisan test --filter=TelefoneTest && php artisan test`
Expected: PASS.

- [ ] **Step 5: Commit** (pular)

```bash
git add app/Support/Telefone.php app/Rules/TelefoneE164.php tests/Unit/TelefoneTest.php
git commit -m "feat(P5): definicao unica de telefone em E.164"
```

---

### Task 9: Telefone como texto E.164 no banco, na validação e nas três telas (P5, parte 2)

**Causa raiz:** a mesma da Tarefa 8, agora aplicada ao schema, aos dados e às telas.

**Files:**
- Create: `app/Services/Leads/NormalizacaoDeTelefones.php`
- Create: `app/Console/Commands/NormalizarTelefones.php`
- Create: `database/migrations/2026_09_28_120001_telefone_como_texto_e164.php`
- Create: `resources/js/utils/telefone.js`
- Modify: `app/Http/Requests/UsuarioRequest.php` (`prepareForValidation`, regras e mensagens de `telefone`)
- Modify: `database/factories/UsuarioFactory.php:29`
- Modify: `resources/js/Pages/Dashboard.vue` (linhas 152, 170, 574 e input 641-650)
- Modify: `resources/js/Pages/Usuario/Perfil.vue` (linhas 85, 120, 410-412 e input 473-479)
- Modify: `resources/js/Pages/Kanban.vue` (linhas 199 e 205, input 512)
- Test: `tests/Feature/Leads/TelefoneDoLeadTest.php`, `tests/Feature/Leads/NormalizacaoDeTelefonesTest.php`

**Interfaces:**
- Consumes: `Telefone`, `TelefoneE164` (Tarefa 8).
- Produces:
  - `usuarios.telefone` como `string(20)` nullable, em E.164;
  - o comando `telefones:normalizar {--dry-run}`;
  - `NormalizacaoDeTelefones::executar(bool $simular): array{alterados:int, invalidos:list<array{id:int,telefone:string}>}`;
  - no front, `formatarTelefone(valor)` e `MASCARA_TELEFONE`.

- [ ] **Step 1: Pré-validação nos dados reais, antes de qualquer código de schema**

A Tarefa 0 já mostrou a distribuição por número de dígitos. Guarde a lista de ids fora do padrão (nem 10 nem 11 dígitos), porque ela será comparada com o relatório do comando:

```bash
mysql -u root -p CRMLeader -e "SELECT id, telefone FROM usuarios WHERE LENGTH(CAST(telefone AS CHAR)) NOT IN (10, 11) ORDER BY id;" | tee "$SCRATCH/telefones-fora-do-padrao.txt"
```

- [ ] **Step 2: Escrever os testes que falham**

`tests/Feature/Leads/TelefoneDoLeadTest.php`:

```php
<?php

namespace Tests\Feature\Leads;

use App\Models\User;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * P5: o servidor normaliza qualquer forma de telefone — com máscara, sem
 * máscara, do quick-add do Kanban que não tem máscara nenhuma — e guarda
 * E.164. O front deixa de ser responsável por isso.
 */
class TelefoneDoLeadTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private User $dono;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = $this->novoTenant();
        $this->dono = $this->agente($tenant);
    }

    private function criar(array $dados)
    {
        return $this->actingAs($this->dono)->postJson('/api/usuarios', array_merge([
            'nome' => 'Cliente Teste',
            'email' => 'cliente'.uniqid().'@exemplo.com',
        ], $dados));
    }

    public function test_telefone_com_mascara_e_gravado_em_e164(): void
    {
        $this->criar(['telefone' => '(11) 99999-8888'])->assertCreated();

        $this->assertDatabaseHas('usuarios', ['telefone' => '+5511999998888']);
    }

    public function test_fixo_de_10_digitos_e_aceito(): void
    {
        $this->criar(['telefone' => '1133334444'])->assertCreated();

        $this->assertDatabaseHas('usuarios', ['telefone' => '+551133334444']);
    }

    /** Review Focus 3 */
    public function test_telefone_e_opcional(): void
    {
        $this->criar([])->assertCreated();
        $this->criar(['telefone' => ''])->assertCreated();

        $this->assertSame(2, Usuario::whereNull('telefone')->count());
    }

    public function test_telefone_invalido_e_recusado_com_mensagem(): void
    {
        $this->criar(['telefone' => '123'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['telefone' => 'com DDD']);
    }

    public function test_telefone_como_array_e_recusado_sem_erro_500(): void
    {
        $this->criar(['telefone' => ['11999998888']])->assertUnprocessable();
    }

    public function test_edicao_usa_a_mesma_regra(): void
    {
        $lead = $this->lead(app(\App\Support\Tenancy\CurrentTenant::class)->get(), $this->dono);

        $this->actingAs($this->dono)->putJson("/api/usuarios/{$lead->id}", [
            'nome' => 'Cliente Editado',
            'email' => $lead->email,
            'telefone' => '(21) 3333-4444',
        ])->assertOk();

        $this->assertSame('+552133334444', $lead->fresh()->telefone);
    }
}
```

`tests/Feature/Leads/NormalizacaoDeTelefonesTest.php`:

```php
<?php

namespace Tests\Feature\Leads;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class NormalizacaoDeTelefonesTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private int $bom;
    private int $ruim;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);

        // Direto na tabela: é o formato que as linhas antigas têm depois de a
        // coluna virar texto (só dígitos, sem "+").
        $this->bom = $this->lead($tenant, $dono)->id;
        $this->ruim = $this->lead($tenant, $dono)->id;
        DB::table('usuarios')->where('id', $this->bom)->update(['telefone' => '11999998888']);
        DB::table('usuarios')->where('id', $this->ruim)->update(['telefone' => '123']);
    }

    public function test_dry_run_relata_e_nao_altera(): void
    {
        $this->artisan('telefones:normalizar', ['--dry-run' => true])
            ->expectsOutputToContain('123')
            ->assertSuccessful();

        $this->assertSame('11999998888', DB::table('usuarios')->where('id', $this->bom)->value('telefone'));
    }

    public function test_normaliza_o_que_reconhece_e_preserva_o_resto(): void
    {
        $this->artisan('telefones:normalizar')->assertSuccessful();

        $this->assertSame('+5511999998888', DB::table('usuarios')->where('id', $this->bom)->value('telefone'));
        $this->assertSame('123', DB::table('usuarios')->where('id', $this->ruim)->value('telefone'));
    }

    public function test_e_idempotente(): void
    {
        $this->artisan('telefones:normalizar')->assertSuccessful();
        $this->artisan('telefones:normalizar')->expectsOutputToContain('0 telefone(s) alterado(s)')->assertSuccessful();
    }
}
```

- [ ] **Step 3: Rodar e confirmar que falha**

Run: `php artisan test --filter='TelefoneDoLeadTest|NormalizacaoDeTelefonesTest'`
Expected: FAIL. `'+5511…'` não cabe em BIGINT (erro de banco), o telefone vazio é recusado e o comando não existe.

- [ ] **Step 4: Serviço e comando**

`app/Services/Leads/NormalizacaoDeTelefones.php`:

```php
<?php

namespace App\Services\Leads;

use App\Rules\TelefoneE164;
use App\Support\Telefone;
use Illuminate\Support\Facades\DB;

/**
 * Converte os telefones gravados para E.164 com a MESMA função que valida as
 * entradas novas (Telefone::normalizar). O que não é reconhecido é relatado e
 * mantido como está — nunca descartado.
 *
 * DB::table e não Eloquent: roda na migration e no console, sem tenant ativo,
 * e não deve disparar observers (não é uma edição do lead por alguém).
 */
class NormalizacaoDeTelefones
{
    /** @return array{alterados:int, invalidos:list<array{id:int,telefone:string}>} */
    public function executar(bool $simular): array
    {
        $alterados = 0;
        $invalidos = [];

        DB::table('usuarios')->whereNotNull('telefone')
            ->chunkById(500, function ($linhas) use ($simular, &$alterados, &$invalidos) {
                foreach ($linhas as $linha) {
                    $atual = (string) $linha->telefone;
                    $novo = Telefone::normalizar($atual);

                    if (! TelefoneE164::valido($novo)) {
                        $invalidos[] = ['id' => (int) $linha->id, 'telefone' => $atual];
                        continue;
                    }

                    if ($novo === $atual) {
                        continue;
                    }

                    $alterados++;

                    if (! $simular) {
                        DB::table('usuarios')->where('id', $linha->id)->update(['telefone' => $novo]);
                    }
                }
            });

        return ['alterados' => $alterados, 'invalidos' => $invalidos];
    }
}
```

`app/Console/Commands/NormalizarTelefones.php`:

```php
<?php

namespace App\Console\Commands;

use App\Services\Leads\NormalizacaoDeTelefones;
use Illuminate\Console\Command;

class NormalizarTelefones extends Command
{
    protected $signature = 'telefones:normalizar {--dry-run : Só relata o que faria}';

    protected $description = 'Converte os telefones dos leads para E.164 e lista os que não são reconhecidos';

    public function handle(NormalizacaoDeTelefones $normalizacao): int
    {
        $simular = (bool) $this->option('dry-run');
        $resultado = $normalizacao->executar($simular);

        $this->line(($simular ? '[dry-run] ' : '').$resultado['alterados'].' telefone(s) '.($simular ? 'seriam alterados' : 'alterado(s)').'.');

        if ($resultado['invalidos']) {
            $this->warn('Não reconhecidos (mantidos como estão; corrigir pela tela do lead):');
            $this->table(['Lead', 'Telefone'], $resultado['invalidos']);
        }

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Rodar o dry-run contra o dev, ainda com a coluna BIGINT**

```bash
php artisan telefones:normalizar --dry-run | tee "$SCRATCH/telefones-dry-run.txt"
```

Expected: a lista de "não reconhecidos" bate com `$SCRATCH/telefones-fora-do-padrao.txt` do Step 1. **Se houver divergência, pare e mostre os dois arquivos ao usuário.**

- [ ] **Step 6: Migration**

`database/migrations/2026_09_28_120001_telefone_como_texto_e164.php`:

```php
<?php

use App\Services\Leads\NormalizacaoDeTelefones;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Telefone deixa de ser número. BIGINT perdia o zero à esquerda e não
 * guardava "+", e o WhatsApp exige E.164.
 *
 * O MySQL converte o inteiro para a string de dígitos no ALTER; a conversão
 * para E.164 usa a mesma função que valida as entradas novas.
 *
 * down() é LOSSY: tira tudo que não é dígito para caber de volta em BIGINT, e
 * telefone vazio vira 0 (a coluna antiga era NOT NULL).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('telefone', 20)->nullable()->change();
        });

        app(NormalizacaoDeTelefones::class)->executar(simular: false);
    }

    public function down(): void
    {
        DB::table('usuarios')->chunkById(500, function ($linhas) {
            foreach ($linhas as $linha) {
                DB::table('usuarios')->where('id', $linha->id)
                    ->update(['telefone' => preg_replace('/\D/', '', (string) $linha->telefone) ?: '0']);
            }
        });

        Schema::table('usuarios', function (Blueprint $table) {
            $table->bigInteger('telefone')->change();
        });
    }
};
```

- [ ] **Step 7: Validação normaliza no servidor**

Em `app/Http/Requests/UsuarioRequest.php`, adicione `use App\Rules\TelefoneE164;` e `use App\Support\Telefone;`, mais este método:

```php
    /**
     * O telefone chega em qualquer forma — com máscara (Dashboard, Perfil),
     * sem máscara (quick-add do Kanban) — e é normalizado aqui, antes das
     * regras. Nenhuma tela precisa mais tirar a máscara.
     *
     * Só texto é normalizado: um array segue como veio e a regra `string`
     * recusa, sem erro 500.
     */
    protected function prepareForValidation(): void
    {
        $telefone = $this->input('telefone');

        if ($telefone === null || is_string($telefone) || is_int($telefone)) {
            $this->merge(['telefone' => Telefone::normalizar($telefone === null ? null : (string) $telefone)]);
        }
    }
```

Nas duas funções de regras, o telefone passa a ser igual:

```php
            'telefone' => ['nullable', 'string', new TelefoneE164()],
```

Em `messages()`, remova `telefone.required` e `telefone.min` e mantenha `telefone.string`.

- [ ] **Step 8: Factory em E.164**

Em `database/factories/UsuarioFactory.php:29`:

```php
            'telefone' => '+55'.fake()->numerify('119########'),
```

- [ ] **Step 9: Front com um utilitário só**

`resources/js/utils/telefone.js`:

```js
// Exibição do telefone. O servidor guarda E.164 (+5511999998888) e é quem
// normaliza a entrada — as telas só mascaram e formatam.
export const MASCARA_TELEFONE = '["(##) ####-####", "(##) #####-####"]';

export function formatarTelefone(valor) {
    if (!valor) return '';
    const texto = String(valor);
    const m = texto.match(/^\+55(\d{2})(\d{4,5})(\d{4})$/);
    return m ? `(${m[1]}) ${m[2]}-${m[3]}` : texto;
}
```

**Dashboard.vue:**
- importe com `import { formatarTelefone, MASCARA_TELEFONE } from '@/utils/telefone';`;
- linha 152: `telefone: String(usuario.telefone || '')` → `telefone: usuario.telefone ?? ''`;
- linha 170: remova `payload.telefone = payload.telefone.replace(/\D/g, '');`;
- linha 574: `{{ usuario.telefone || '—' }}` → `{{ formatarTelefone(usuario.telefone) || '—' }}`;
- input 641-650: `data-maska="(##) #####-####"` → `:data-maska="MASCARA_TELEFONE"`;
- se o label ou o placeholder do campo tiver `*`, tire: o campo agora é opcional.

**Perfil.vue:**
- importe o mesmo;
- linha 85 (`buscarUsuario`): depois de `usuario.value = response.data[0];` adicione `usuario.value.telefone = formatarTelefone(usuario.value.telefone);`. O input mascarado passa a mostrar `(11) 99999-8888`;
- linha 120: remova `usuario.value.telefone = usuario.value.telefone?.replace(/\D/g, '');`. Ela alterava o próprio v-model, e com um número vindo do banco daria TypeError;
- linhas 410-412: `{{ usuario.telefone }}` continua, porque já vem formatado do `buscarUsuario`;
- input 473-479: `data-maska="(##) #####-####"` → `:data-maska="MASCARA_TELEFONE"`.

**Kanban.vue:**
- importe `MASCARA_TELEFONE` e o `vMaska` (`import { vMaska } from 'maska/vue';`, como em `Dashboard.vue:6`);
- linha 199: a guarda `|| !quickAddForm.value.telefone.trim()` sai, e o telefone fica opcional (Review Focus 3);
- input 512: acrescente `v-maska :data-maska="MASCARA_TELEFONE"` e troque o placeholder `"Telefone *"` por `"Telefone"`;
- linha 205: continua enviando `quickAddForm.value.telefone.trim()`, e o servidor normaliza.

Confira que nenhuma tela ainda tira a máscara por conta própria:

```bash
grep -rn "telefone.*replace(/\\\\D" resources/js
```

Expected: nenhuma ocorrência.

- [ ] **Step 10: Rodar os testes da tarefa, a suíte inteira e o build**

Run: `php artisan test --filter='TelefoneDoLeadTest|NormalizacaoDeTelefonesTest|TelefoneTest' && php artisan test && npm run build`
Expected: PASS. Os testes antigos que mandam `'telefone' => '11999998888'` continuam passando, porque o servidor normaliza.

- [ ] **Step 11: Aplicar em dev e conferir**

```bash
php artisan migrate
mysql -u root -p CRMLeader -e "SELECT telefone FROM usuarios WHERE telefone NOT LIKE '+%' OR telefone IS NULL;" | tee "$SCRATCH/telefones-depois.txt"
```

Expected: só os ids do relatório de inválidos (mais os nulos, se houver).

- [ ] **Step 12: Commit** (pular)

```bash
git add app/Services/Leads app/Console/Commands/NormalizarTelefones.php database/migrations/2026_09_28_120001_telefone_como_texto_e164.php app/Http/Requests/UsuarioRequest.php database/factories/UsuarioFactory.php resources/js/utils/telefone.js resources/js/Pages/Dashboard.vue resources/js/Pages/Usuario/Perfil.vue resources/js/Pages/Kanban.vue tests/Feature/Leads
git commit -m "fix(P5): telefone em E.164, opcional, normalizado no servidor"
```

---

### Task 10: `LimitesDeTexto`, o limite do schema e o da validação vindo da mesma fonte (P6)

**Causa raiz:** cada controller valida o texto inline, sem `max`. As colunas são `VARCHAR(255)`. Com o MySQL em modo strict, o texto longo vira erro 500 em vez de 422.

**Files:**
- Create: `app/Support/LimitesDeTexto.php`
- Create: `database/migrations/2026_09_28_130001_textos_longos_como_text.php`
- Modify: `app/Http/Controllers/Userarios.php` (`createAnotacao` na linha 246, `updateAnotacao` na linha 266)
- Modify: `app/Http/Controllers/ProjetoController.php` (`createAnotacao` na linha 114, `updateAnotacao` na linha 136)
- Modify: `app/Http/Requests/UsuarioRequest.php`, `app/Http/Requests/ProjetoRequest.php` (`nome`, `email`, `descricao`)
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (prop compartilhada `limites`)
- Modify: `resources/js/Pages/Usuario/Perfil.vue` (textareas nas linhas 483, 629 e 650), `resources/js/Pages/Usuario/ProjetoPanel.vue` (textareas nas linhas 409, 554, 594 e 708), `resources/js/Pages/Dashboard.vue` (input na linha 655)
- Test: `tests/Feature/DataIntegrity/LimitesDeTextoTest.php`

**Interfaces:**
- Produces:
  - `LimitesDeTexto::NOME` = 255
  - `LimitesDeTexto::EMAIL` = 255
  - `LimitesDeTexto::DESCRICAO` = 5000
  - `LimitesDeTexto::ANOTACAO` = 10000
  - `LimitesDeTexto::paraOFront(): array`
  - prop Inertia `limites` = `{nome, email, descricao, anotacao}`

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\DataIntegrity;

use App\Models\Anotacao;
use App\Models\Projeto;
use App\Models\User;
use App\Models\Usuario;
use App\Support\LimitesDeTexto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

/**
 * P6: todo texto livre tem limite, o limite cabe na coluna, e passar dele é
 * 422 — nunca 500 de banco nem texto cortado em silêncio.
 */
class LimitesDeTextoTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private User $dono;
    private Usuario $lead;
    private Projeto $projeto;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = $this->novoTenant();
        $this->dono = $this->agente($tenant);
        $this->lead = $this->lead($tenant, $this->dono);
        $this->projeto = $this->projeto($this->lead);
    }

    public function test_anotacao_de_lead_aceita_o_limite_e_recusa_um_a_mais(): void
    {
        $this->actingAs($this->dono)->postJson('/api/anotacao', [
            'usuario_id' => $this->lead->id, 'descricao' => str_repeat('a', LimitesDeTexto::ANOTACAO),
        ])->assertCreated();

        $this->actingAs($this->dono)->postJson('/api/anotacao', [
            'usuario_id' => $this->lead->id, 'descricao' => str_repeat('a', LimitesDeTexto::ANOTACAO + 1),
        ])->assertUnprocessable();
    }

    public function test_resumo_de_reuniao_de_2000_caracteres_e_salvo_inteiro(): void
    {
        $texto = str_repeat('Reunião: próximos passos. ', 80); // 2080 caracteres, com acento

        $this->actingAs($this->dono)->postJson('/api/anotacao', [
            'usuario_id' => $this->lead->id, 'descricao' => $texto,
        ])->assertCreated();

        $this->assertSame($texto, Anotacao::firstOrFail()->descricao);
    }

    public function test_edicao_de_anotacao_tambem_tem_limite(): void
    {
        $anotacao = Anotacao::create(['descricao' => 'curta', 'usuario_id' => $this->lead->id]);

        $this->actingAs($this->dono)->putJson("/api/anotacao/{$anotacao->id}", [
            'descricao' => str_repeat('a', LimitesDeTexto::ANOTACAO + 1),
        ])->assertUnprocessable();
    }

    public function test_anotacao_de_projeto_tem_o_mesmo_limite(): void
    {
        $this->actingAs($this->dono)->postJson('/api/projetoAnotacao', [
            'projeto_id' => $this->projeto->id, 'descricao' => str_repeat('a', LimitesDeTexto::ANOTACAO),
        ])->assertCreated();

        $this->actingAs($this->dono)->postJson('/api/projetoAnotacao', [
            'projeto_id' => $this->projeto->id, 'descricao' => str_repeat('a', LimitesDeTexto::ANOTACAO + 1),
        ])->assertUnprocessable();
    }

    public function test_campos_do_lead_tem_limite(): void
    {
        $base = ['nome' => 'Cliente Teste', 'email' => 'c@exemplo.com'];

        $this->actingAs($this->dono)->postJson('/api/usuarios', [...$base, 'nome' => str_repeat('a', 256)])
            ->assertUnprocessable()->assertJsonValidationErrors('nome');
        $this->actingAs($this->dono)->postJson('/api/usuarios', [...$base, 'email' => str_repeat('a', 244).'@exemplo.com'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->actingAs($this->dono)->postJson('/api/usuarios', [...$base, 'descricao' => str_repeat('a', LimitesDeTexto::DESCRICAO + 1)])
            ->assertUnprocessable()->assertJsonValidationErrors('descricao');
        $this->actingAs($this->dono)->postJson('/api/usuarios', [...$base, 'descricao' => str_repeat('a', LimitesDeTexto::DESCRICAO)])
            ->assertCreated();
    }

    public function test_descricao_do_projeto_tem_limite(): void
    {
        $this->actingAs($this->dono)->putJson("/api/projeto/{$this->projeto->id}", [
            'nome' => 'Proposta inicial',
            'status_id' => $this->projeto->status_id,
            'descricao' => str_repeat('a', LimitesDeTexto::DESCRICAO + 1),
        ])->assertUnprocessable();
    }

    public function test_front_recebe_os_mesmos_limites(): void
    {
        $this->actingAs($this->dono)->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('limites.anotacao', LimitesDeTexto::ANOTACAO)
                ->where('limites.descricao', LimitesDeTexto::DESCRICAO));
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=LimitesDeTextoTest`
Expected: FAIL. A anotação no limite dá 500 (strict mode, VARCHAR 255), o texto acima do limite passa na validação e a prop `limites` não existe.

- [ ] **Step 3: A fonte única**

`app/Support/LimitesDeTexto.php`:

```php
<?php

namespace App\Support;

/**
 * Tamanho máximo de cada texto livre, num lugar só: a validação usa estes
 * números, as colunas cabem neles (TEXT = 65.535 bytes; 10.000 caracteres em
 * UTF-8 de até 4 bytes cabem com folga até 16k — ver migration 130001), e o
 * front recebe os mesmos valores por prop compartilhada.
 */
final class LimitesDeTexto
{
    public const NOME = 255;
    public const EMAIL = 255;
    public const DESCRICAO = 5000;
    public const ANOTACAO = 10000;

    public static function paraOFront(): array
    {
        return [
            'nome' => self::NOME,
            'email' => self::EMAIL,
            'descricao' => self::DESCRICAO,
            'anotacao' => self::ANOTACAO,
        ];
    }
}
```

> Conta de bytes: 10.000 caracteres × 4 bytes (pior caso utf8mb4) = 40.000 bytes, abaixo dos 65.535 do TEXT.

- [ ] **Step 4: Migration**

`database/migrations/2026_09_28_130001_textos_longos_como_text.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * VARCHAR(255) -> TEXT onde o usuário escreve livremente. Ver
 * App\Support\LimitesDeTexto. Nullable repetido de cada coluna original: o
 * ->change() do Laravel 11 descarta o que não for repetido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anotacaos', fn (Blueprint $t) => $t->text('descricao')->change());
        Schema::table('projetoAnotacaos', fn (Blueprint $t) => $t->text('descricao')->change());
        Schema::table('usuarios', fn (Blueprint $t) => $t->text('descricao')->nullable()->change());
        Schema::table('projetos', fn (Blueprint $t) => $t->text('descricao')->nullable()->change());
    }

    public function down(): void
    {
        // LOSSY se houver texto > 255: o MySQL em strict recusa o ALTER, e é
        // o comportamento desejado (não cortar texto do usuário em silêncio).
        Schema::table('anotacaos', fn (Blueprint $t) => $t->string('descricao')->change());
        Schema::table('projetoAnotacaos', fn (Blueprint $t) => $t->string('descricao')->change());
        Schema::table('usuarios', fn (Blueprint $t) => $t->string('descricao')->nullable()->change());
        Schema::table('projetos', fn (Blueprint $t) => $t->string('descricao')->nullable()->change());
    }
};
```

- [ ] **Step 5: As regras usam a fonte única**

Adicione `use App\Support\LimitesDeTexto;` nos quatro arquivos.

- `Userarios.php:246` e `:266`: `'descricao' => 'required|string'` → `'descricao' => ['required', 'string', 'max:'.LimitesDeTexto::ANOTACAO]`
- `ProjetoController.php:114` e `:136`: a mesma troca.
- `UsuarioRequest.php`, em `storeRules()` e `updateRules()`:
  ```php
            'nome' => ['required', 'string', 'min:5', 'max:'.LimitesDeTexto::NOME],
            // a regra de email ganha 'max:'.LimitesDeTexto::EMAIL antes de EmailDeLeadDisponivel
            'descricao' => ['nullable', 'string', 'max:'.LimitesDeTexto::DESCRICAO],
  ```
  Em `messages()`, adicione `'nome.max' => 'O nome pode ter no máximo :max caracteres.'`, `'email.max' => 'O email pode ter no máximo :max caracteres.'` e `'descricao.max' => 'A descrição pode ter no máximo :max caracteres.'`.
- `ProjetoRequest.php`, em `storeRules()` e `updateRules()`:
  ```php
            'nome' => ['required', 'string', 'min:5', 'max:'.LimitesDeTexto::NOME],
            'descricao' => ['nullable', 'string', 'max:'.LimitesDeTexto::DESCRICAO],
  ```

- [ ] **Step 6: Prop compartilhada e `maxlength` no front**

Em `HandleInertiaRequests::share()`, depois de `'auth' => [...]`:

```php
            'limites' => \App\Support\LimitesDeTexto::paraOFront(),
```

Nos componentes, leia `const limites = usePage().props.limites;` (importe `usePage` de `@inertiajs/vue3` onde ainda não estiver importado) e aplique:
- textareas de anotação (`Perfil.vue:629,650`; `ProjetoPanel.vue:554,594`): `:maxlength="limites.anotacao"`;
- descrição de lead e de projeto (`Perfil.vue:483`; `Dashboard.vue:655`; `ProjetoPanel.vue:409,708`): `:maxlength="limites.descricao"`.

Nas duas textareas de anotação nova (`Perfil.vue:629`, `ProjetoPanel.vue:554`), acrescente logo abaixo um contador discreto, com a classe de texto secundário que o arquivo já usa:

```vue
<small class="edit-label">{{ (novaAnotacao?.length ?? 0) }} / {{ limites.anotacao }}</small>
```

Use o nome real do v-model de cada textarea no lugar de `novaAnotacao` (confira no arquivo).

- [ ] **Step 7: Rodar o teste da tarefa, a suíte inteira e o build**

Run: `php artisan test --filter=LimitesDeTextoTest && php artisan test && npm run build`
Expected: PASS.

- [ ] **Step 8: Aplicar em dev**

Run: `php artisan migrate`
Expected: a migration 130001 roda sem erro. Nenhum dado é alterado, porque o conteúdo de VARCHAR cabe em TEXT.

- [ ] **Step 9: Commit** (pular)

```bash
git add app/Support/LimitesDeTexto.php database/migrations/2026_09_28_130001_textos_longos_como_text.php app/Http/Controllers/Userarios.php app/Http/Controllers/ProjetoController.php app/Http/Requests/UsuarioRequest.php app/Http/Requests/ProjetoRequest.php app/Http/Middleware/HandleInertiaRequests.php resources/js/Pages tests/Feature/DataIntegrity/LimitesDeTextoTest.php
git commit -m "fix(P6): limites de texto numa fonte unica, colunas TEXT"
```

---

## Fase 4: Robustez (P8)

Vem antes da paginação porque a troca de POST para GET afeta os mesmos chamadores do Dashboard e do Kanban que a Tarefa 14 reescreve.

### Task 11: `kanbanSettings` valida o estágio do próprio tenant

**Causa raiz:** o valor do corpo vai direto para `users.kanban_default_estagio_id`, e qualquer exceção vira 404 "Usuário não encontrado". A FK só confere se o id existe em *algum* tenant. Na prática, isso aceita estágio de outra empresa e funciona como oráculo de existência de ids (200 para quem existe, 404 para quem não existe).

**Files:**
- Modify: `app/Http/Controllers/Userarios.php:424-432`
- Modify: `resources/js/Pages/Kanban.vue:302-305` (remover o `user_id`, que o servidor ignora)
- Test: `tests/Feature/Funis/PreferenciaDoKanbanTest.php`

**Interfaces:**
- Produces: `PATCH /api/kanban/settings` responde 200 com estágio válido ou `null`, e 422 em qualquer outro caso.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Funis;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class PreferenciaDoKanbanTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;
    private User $agente;
    private Estagio $estagio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->novoTenant();
        $this->agente = $this->agente($this->tenant);
        $funil = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
        $this->estagio = Estagio::factory()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);
    }

    private function salvar(mixed $valor)
    {
        return $this->actingAs($this->agente)->patchJson('/api/kanban/settings', ['default_estagio_id' => $valor]);
    }

    public function test_estagio_do_tenant_e_salvo(): void
    {
        $this->salvar($this->estagio->id)->assertOk();
        $this->assertSame($this->estagio->id, $this->agente->fresh()->kanban_default_estagio_id);
    }

    public function test_null_limpa_a_preferencia(): void
    {
        $this->salvar($this->estagio->id)->assertOk();
        $this->salvar(null)->assertOk();
        $this->assertNull($this->agente->fresh()->kanban_default_estagio_id);
    }

    public function test_estagio_de_outro_tenant_e_recusado(): void
    {
        $outro = $this->novoTenant();
        $funilAlheio = Funil::factory()->padrao()->create(['tenant_id' => $outro->id]);
        $alheio = Estagio::factory()->create(['tenant_id' => $outro->id, 'funil_id' => $funilAlheio->id]);
        $this->ativar($this->tenant);

        $this->salvar($alheio->id)->assertUnprocessable();
        $this->assertNull($this->agente->fresh()->kanban_default_estagio_id);
    }

    public function test_inexistente_arquivado_e_array_sao_recusados_com_422(): void
    {
        $this->salvar(999999)->assertUnprocessable();
        $this->salvar(['1'])->assertUnprocessable();

        $this->estagio->delete();
        $this->salvar($this->estagio->id)->assertUnprocessable();
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=PreferenciaDoKanbanTest`
Expected: FAIL. O estágio de outro tenant é salvo (200), e o id inexistente responde 404.

- [ ] **Step 3: Implementar**

```php
    public function kanbanSettings(Request $request)
    {
        // Sem try/catch: o catch(\Exception) anterior transformava qualquer
        // falha — inclusive a FK recusando um id — em 404 "Usuário não
        // encontrado", o que também servia de oráculo de ids entre tenants.
        $validated = $request->validate([
            'default_estagio_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('estagios', 'id')
                    ->where('tenant_id', app(CurrentTenant::class)->id())
                    ->whereNull('deleted_at'),
            ],
        ]);

        auth()->user()->update(['kanban_default_estagio_id' => $validated['default_estagio_id']]);

        return response()->json(['message' => 'Preferência salva']);
    }
```

Em `Kanban.vue:302-305`, o payload passa a ser `{ default_estagio_id: defaultEstagioId.value }`.

- [ ] **Step 4: Rodar o teste da tarefa, a suíte inteira e o build**

Run: `php artisan test --filter=PreferenciaDoKanbanTest && php artisan test && npm run build`
Expected: PASS.

- [ ] **Step 5: Commit** (pular)

```bash
git add app/Http/Controllers/Userarios.php resources/js/Pages/Kanban.vue tests/Feature/Funis/PreferenciaDoKanbanTest.php
git commit -m "fix(P8): preferencia do kanban valida estagio do tenant"
```

---

### Task 12: Leitura é GET: fim do POST disfarçado e do `user_id` ignorado

**Causa raiz:** uma convenção antiga do projeto. Leitura via POST não é cacheável, fica fora da varredura de leitura do `TodaRotaDeEscritaAutorizaTest` (e precisa de isenção na de escrita) e carrega um `user_id` que o servidor ignora. Isso sugere ao leitor um controle que não existe.

**Files:**
- Modify: `routes/api.php` (linhas 21, 22, 76, 77, 94, 97 e 120)
- Modify: `app/Http/Controllers/arquivo.php:34` (a chave `user_id` passa a ser `usuario_id`)
- Modify:
  - `resources/js/Pages/Dashboard.vue:53,63,82`
  - `resources/js/Pages/Kanban.vue:111-114,250`
  - `resources/js/Pages/Usuario/Perfil.vue:155,251,311`
  - `resources/js/Pages/Usuario/ProjetoPanel.vue:141,148`
- Modify: `tests/Feature/Autorizacao/TodaRotaDeEscritaAutorizaTest.php` (remover isenções)
- Modify (chamadas nos testes):
  - `tests/Feature/Autorizacao/UmSaltoPolicyTest.php:136,140`
  - `tests/Feature/Autorizacao/DonoNoCorpoTest.php:63,67`
  - `tests/Feature/DataIntegrity/KanbanArchivedEstagioTest.php:59,75,86,108`
  - `tests/Feature/Autorizacao/VisibilidadeDeLeadsTest.php:72,80,81`
  - `tests/Feature/Auth/RegistrationTest.php:44,48`
  - `tests/Feature/Arquivos/DownloadProtegidoTest.php`
  - `tests/Feature/Exclusao/LixeiraDeLeadTest.php`
  - `tests/Feature/Exclusao/LixeiraDeProjetoTest.php`
- Test: `tests/Feature/Navegacao/VerbosDeLeituraTest.php`

**Interfaces:**
- Produces:
  - `GET /api/estagios?funil_id=`
  - `GET /api/status`
  - `GET /api/metricas`
  - `GET /api/kanban?funil_id=`
  - `GET /api/tarefasPendentes`
  - `GET /api/projetos?usuario_id=`
  - `GET /api/arquivos?usuario_id=`
  - `POST /api/buscarArquivo` deixa de existir.
  - `POST /api/pegarUsuarios` **continua** até a Tarefa 14.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Navegacao;

use App\Models\User;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class VerbosDeLeituraTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private User $dono;
    private Usuario $lead;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = $this->novoTenant();
        $this->dono = $this->agente($tenant);
        $this->lead = $this->lead($tenant, $this->dono);
    }

    public static function leituras(): array
    {
        return [['/api/estagios'], ['/api/status'], ['/api/metricas'], ['/api/kanban'], ['/api/tarefasPendentes']];
    }

    #[DataProvider('leituras')]
    public function test_leitura_responde_a_get_e_nao_a_post(string $url): void
    {
        $this->actingAs($this->dono)->getJson($url)->assertOk();
        $this->actingAs($this->dono)->postJson($url)->assertMethodNotAllowed();
    }

    public function test_projetos_e_arquivos_do_lead_por_query_string(): void
    {
        $this->actingAs($this->dono)->getJson("/api/projetos?usuario_id={$this->lead->id}")->assertOk();
        $this->actingAs($this->dono)->getJson("/api/arquivos?usuario_id={$this->lead->id}")->assertOk();
        $this->actingAs($this->dono)->postJson('/api/buscarArquivo', ['user_id' => $this->lead->id])->assertNotFound();
    }

    public function test_arquivos_de_lead_alheio_continuam_404(): void
    {
        $colega = $this->agente(app(\App\Support\Tenancy\CurrentTenant::class)->get());

        $this->actingAs($colega)->getJson("/api/arquivos?usuario_id={$this->lead->id}")->assertNotFound();
        $this->actingAs($colega)->getJson("/api/projetos?usuario_id={$this->lead->id}")->assertNotFound();
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=VerbosDeLeituraTest`
Expected: FAIL com 405 nos GET.

- [ ] **Step 3: Rotas**

Em `routes/api.php`:
- `Route::post('/estagios', …)` → `Route::get('/estagios', …)`
- `Route::post('/status', …)` → `Route::get('/status', …)`
- `Route::post('/metricas', …)` → `Route::get('/metricas', …)`
- `Route::post('/kanban', …)` → `Route::get('/kanban', …)`. Mantenha `Route::patch('/kanban/settings', …)` como está.
- `Route::post('/tarefasPendentes', …)` → `Route::get('/tarefasPendentes', …)`
- `Route::post('/projetos', …)` → `Route::get('/projetos', …)`
- remova `Route::post('buscarArquivo', …)`. O `GET /api/arquivos` do `apiResource` já aponta para `arquivo::index`.

Em `app/Http/Controllers/arquivo.php:34`, troque `$request->integer('user_id')` por `$request->integer('usuario_id')` e substitua o parágrafo do comentário que justificava a chave `user_id` por:

```php
        // `usuario_id` (id do LEAD), por query string: GET /api/arquivos?usuario_id=.
        // Era `user_id` no corpo de um POST, nome que sugeria o agente.
```

Os controllers `estagios`, `kanban`, `ProjetoController::view` e `pendentes` leem `$request->filled(...)`/`$request->integer(...)`, que funcionam igual com query string. Não precisam mudar.

- [ ] **Step 4: Front**

Cada chamada passa a ser `axios.get(url, { params })`, sem `user_id`:

| Arquivo:linha | Antes | Depois |
|---|---|---|
| `Dashboard.vue:53` | `axios.post('/api/metricas', { user_id: user.value.id })` | `axios.get('/api/metricas')` |
| `Dashboard.vue:63` | `axios.post('/api/tarefasPendentes', { user_id: … })` | `axios.get('/api/tarefasPendentes')` |
| `Dashboard.vue:82` | `axios.post('/api/estagios')` | `axios.get('/api/estagios')` |
| `Kanban.vue:111-114` | `const payload = { user_id: user.value.id }; …; axios.post('/api/kanban', payload)` | `const params = funilId ? { funil_id: funilId } : {}; axios.get('/api/kanban', { params })`, preservando a lógica que já decide o `funil_id` |
| `Kanban.vue:250` | `axios.post('/api/estagios', { funil_id: … })` | `axios.get('/api/estagios', { params: { funil_id: moverFunilId.value } })` |
| `Perfil.vue:155` | `axios.post('/api/estagios' …)` | `axios.get('/api/estagios' …)`, com os mesmos parâmetros em `params` |
| `Perfil.vue:251` | `axios.post('/api/buscarArquivo', { user_id: id })` | `axios.get('/api/arquivos', { params: { usuario_id: id } })` |
| `Perfil.vue:311` | `axios.post('/api/projetos', { usuario_id: idPerfil })` | `axios.get('/api/projetos', { params: { usuario_id: idPerfil } })` |
| `ProjetoPanel.vue:141` | `axios.post('/api/status')` | `axios.get('/api/status')` |
| `ProjetoPanel.vue:148` | `axios.post('/api/projetos', { usuario_id: props.usuarioId })` | `axios.get('/api/projetos', { params: { usuario_id: props.usuarioId } })` |

Se `user` ficar sem uso em `Kanban.vue` ou `Dashboard.vue` depois disso, remova a declaração. Confira:

```bash
grep -rnE "axios\.post\('/api/(estagios|status|metricas|kanban'|tarefasPendentes|projetos|buscarArquivo)" resources/js
grep -rn "user_id: user.value.id" resources/js
```

Expected: nenhuma ocorrência no primeiro grep. No segundo, só `Dashboard.vue:146` (o `/pegarUsuarios`, que a Tarefa 14 remove).

- [ ] **Step 5: Isenções e chamadas nos testes**

Em `TodaRotaDeEscritaAutorizaTest::ISENTAS`, remova as linhas e os comentários de bloco de:
- `'POST api/kanban'`
- `'POST api/metricas'`
- `'POST api/tarefasPendentes'`
- `'POST api/estagios'`
- `'POST api/status'`

Mantenha `'POST api/pegarUsuarios'` com o comentário "Leitura disfarçada de POST…" (a Tarefa 14 a remove).

Nos testes listados em **Files**, troque cada `postJson('/api/<rota>', [chaves])` por `getJson('/api/<rota>?'.http_build_query([chaves]))`. Casos especiais:
- `/api/buscarArquivo`, `['user_id' => $x]` → `getJson('/api/arquivos?usuario_id='.$x)`;
- `DownloadProtegidoTest`: `?user_id=` → `?usuario_id=`.

Confira:

```bash
grep -rnE "postJson\(['\"]/api/(estagios|status|metricas|kanban['\"]|tarefasPendentes|projetos|buscarArquivo)" tests
```

Expected: nenhuma ocorrência.

- [ ] **Step 6: Rodar o teste da tarefa, a suíte inteira e o build**

Run: `php artisan test --filter=VerbosDeLeituraTest && php artisan test && npm run build`
Expected: PASS. `test_toda_rota_de_escrita_autoriza_ou_esta_isenta` não acusa isenção sobrando, porque as rotas agora são GET e não entram na varredura de escrita.

- [ ] **Step 7: Commit** (pular)

```bash
git add routes/api.php app/Http/Controllers/arquivo.php resources/js tests
git commit -m "fix(P8): leituras via GET, sem user_id ignorado"
```

---

### Task 13: Limite de requisições, `APP_DEBUG` seguro por padrão e o comentário dos observers

**Causa raiz:**
- Não existe nenhum `RateLimiter::for` nem `throttleApi`. Só a verificação de e-mail tem `throttle`, e o login conta só por e-mail+IP, o que deixa testar muitos e-mails sem freio.
- O `.env.example` nasce com `APP_DEBUG=true`, e a CI copia esse arquivo.

**Files:**
- Modify: `app/Providers/AppServiceProvider.php` (limiters e comentário dos observers)
- Modify: `bootstrap/app.php` (`throttleApi()`)
- Modify: `routes/auth.php` (login, register, forgot-password e reset-password)
- Modify: `.env.example:4`, `README.md`
- Test: `tests/Feature/Auth/LimiteDeRequisicoesTest.php`

**Interfaces:**
- Produces:
  - limiter `api`: 120 por minuto, por usuário ou IP;
  - limiter `login`: 10 por minuto por IP;
  - limiter `cadastro`: 6 por minuto por IP.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class LimiteDeRequisicoesTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    public function test_api_limita_por_usuario(): void
    {
        $agente = $this->agente($this->novoTenant());

        for ($i = 0; $i < 120; $i++) {
            $this->actingAs($agente)->getJson('/api/funis')->assertOk();
        }

        $this->actingAs($agente)->getJson('/api/funis')->assertTooManyRequests();
    }

    /** O limite por email+IP do LoginRequest não pega quem troca de email a cada tentativa. */
    public function test_login_limita_por_ip_mesmo_trocando_de_email(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post('/login', ['email' => "tentativa{$i}@exemplo.com", 'password' => 'errada'])
                ->assertSessionHasErrors('email');
        }

        $this->post('/login', ['email' => 'mais-uma@exemplo.com', 'password' => 'errada'])
            ->assertTooManyRequests();
    }

    public function test_pedido_de_redefinicao_de_senha_limita_por_ip(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/forgot-password', ['email' => "alguem{$i}@exemplo.com"]);
        }

        $this->post('/forgot-password', ['email' => 'mais@exemplo.com'])->assertTooManyRequests();
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=LimiteDeRequisicoesTest`
Expected: FAIL. A 121ª requisição dá 200, e o 11º login dá 302.

- [ ] **Step 3: Limiters**

Em `app/Providers/AppServiceProvider.php`, adicione:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
```

No fim de `boot()`:

```php
        // Três limites, cada um contra um abuso diferente:
        //   api      — cliente em loop ou raspagem da carteira (por agente);
        //   login    — teste de muitos emails a partir do mesmo IP. O
        //              LoginRequest já limita por email+IP, o que não pega
        //              quem troca de email a cada tentativa;
        //   cadastro — criação de tenants e disparo de emails de redefinição.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('cadastro', fn (Request $request) => Limit::perMinute(6)->by($request->ip()));
```

No mesmo arquivo, troque o comentário acima de `Usuario::observe(...)` por:

```php
        // Alimentam o activity log de lead. Observers só disparam em operações
        // de model. Não há escrita em massa (query builder) em Usuario/Projeto
        // nos controllers; o único desvio real era o ON DELETE CASCADE do
        // banco, removido em 2026_09_28_110002 e travado por
        // tests/Feature/DataIntegrity/SemCascataDestrutivaTest.php.
```

Em `bootstrap/app.php`, logo depois de `$middleware->statefulApi();`:

```php
        // Usa o limiter 'api' definido no AppServiceProvider.
        $middleware->throttleApi();
```

Em `routes/auth.php`:

```php
    Route::post('register', [RegisteredUserController::class, 'store'])->middleware('throttle:cadastro');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:login');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->middleware('throttle:cadastro')
        ->name('password.email');
    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->middleware('throttle:cadastro')
        ->name('password.store');
```

- [ ] **Step 4: `APP_DEBUG` seguro por padrão**

Em `.env.example:4`: `APP_DEBUG=true` → `APP_DEBUG=false`.

Em `README.md`, no passo de setup logo depois de `cp .env.example .env`:

```markdown
> Em desenvolvimento, ligue `APP_DEBUG=true` no seu `.env`. O exemplo vem com `false` para que um
> ambiente novo (inclusive produção copiada do exemplo) não exponha stack trace.
```

- [ ] **Step 5: Rodar o teste da tarefa, a suíte com debug desligado (como a CI vai rodar) e a suíte normal**

Run: `php artisan test --filter=LimiteDeRequisicoesTest && APP_DEBUG=false php artisan test && php artisan test`
Expected: PASS nas três.
- Se `CorpoDo404Test` ou outro teste de corpo de erro falhar só com `APP_DEBUG=false`, é porque ele dependia do stack trace. **Não reverta o `.env.example`.** Corrija o teste para comparar só o que o handler de 404 de `bootstrap/app.php` garante.
- Se algum teste existente passar de 120 requisições à API num único método, ele vai receber 429. Isso é improvável. Se acontecer, reporte antes de mudar o limite.

- [ ] **Step 6: Commit** (pular)

```bash
git add app/Providers/AppServiceProvider.php bootstrap/app.php routes/auth.php .env.example README.md tests/Feature/Auth/LimiteDeRequisicoesTest.php
git commit -m "fix(P8): limites de requisicao e APP_DEBUG=false no exemplo"
```

---

## Fase 5: Paginação (P7)

### Task 14: `ListagemDeLeads`, com filtros e paginação no servidor

**Causa raiz:** o contrato atual é "devolve tudo e o front se vira". `POST /pegarUsuarios` manda a carteira inteira, e busca, filtros e contagens moram em `Dashboard.vue:119-141`.

**Files:**
- Create: `app/Queries/Leads/ListagemDeLeads.php`
- Create: `app/Http/Requests/ListagemDeLeadsRequest.php`
- Create: `database/migrations/2026_09_28_140001_indice_de_criacao_em_usuarios.php`
- Modify: `app/Models/Usuario.php` (relação `projetos()`)
- Modify: `app/Http/Controllers/Userarios.php` (remover `view`, criar `index`)
- Modify: `routes/api.php` (remover `POST /pegarUsuarios`, criar `GET /leads`)
- Modify: `resources/js/Pages/Dashboard.vue` (script: linhas 87-160; template: linhas 381, 435, 517, 536-590)
- Modify: `tests/Feature/Autorizacao/TodaRotaDeEscritaAutorizaTest.php` (remover `'POST api/pegarUsuarios'`)
- Modify: `tests/Feature/Tenancy/TenantIsolationTest.php:31`, `tests/Feature/Autorizacao/VisibilidadeDeLeadsTest.php:54,63`, `tests/Feature/Exclusao/LixeiraDeLeadTest.php`
- Test: `tests/Feature/Leads/ListagemPaginadaTest.php`

**Interfaces:**
- Consumes: `Usuario::scopeVisibleTo`, `SoftDeletes` (Tarefa 5), `Telefone` em E.164 (Tarefa 9).
- Produces:
  - `GET /api/leads?busca=&estagios[]=&de=&ate=&status=&page=&per_page=` → o JSON do `LengthAwarePaginator` (`data`, `current_page`, `last_page`, `per_page`, `total`);
  - `ListagemDeLeads::__invoke(User $user, array $filtros): LengthAwarePaginator`;
  - `ListagemDeLeads::POR_PAGINA` = 25 e `MAX_POR_PAGINA` = 100;
  - `Usuario::projetos(): HasMany`.

- [ ] **Step 1: Escrever o teste que falha**

```php
<?php

namespace Tests\Feature\Leads;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Statu;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class ListagemPaginadaTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;
    private User $vendedor;
    private User $gestor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->novoTenant();
        $this->vendedor = $this->agente($this->tenant);
        $this->gestor = $this->agente($this->tenant, 'gestor');
    }

    private function listar(User $quem, array $filtros = [])
    {
        return $this->actingAs($quem)->getJson('/api/leads?'.http_build_query($filtros))->assertOk();
    }

    private function ids($resposta): array
    {
        return collect($resposta->json('data'))->pluck('id')->sort()->values()->all();
    }

    public function test_pagina_no_servidor(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->lead($this->tenant, $this->vendedor);
        }

        $pagina1 = $this->listar($this->vendedor, ['per_page' => 25]);
        $pagina1->assertJsonCount(25, 'data')->assertJsonPath('total', 30)->assertJsonPath('last_page', 2);

        $this->listar($this->vendedor, ['per_page' => 25, 'page' => 2])->assertJsonCount(5, 'data');
    }

    public function test_per_page_tem_teto(): void
    {
        $this->listar($this->vendedor, ['per_page' => 500])->assertJsonPath('per_page', 100);
    }

    public function test_mais_recente_primeiro(): void
    {
        $this->travel(-2)->days();
        $antigo = $this->lead($this->tenant, $this->vendedor);
        $this->travelBack();
        $novo = $this->lead($this->tenant, $this->vendedor);

        $this->listar($this->vendedor)
            ->assertJsonPath('data.0.id', $novo->id)
            ->assertJsonPath('data.1.id', $antigo->id);
    }

    public function test_visibilidade_e_tenant_continuam_valendo(): void
    {
        $meu = $this->lead($this->tenant, $this->vendedor);
        $doColega = $this->lead($this->tenant, $this->agente($this->tenant));

        $outro = $this->novoTenant();
        $this->lead($outro, $this->agente($outro));
        $this->ativar($this->tenant);

        $this->assertSame([$meu->id], $this->ids($this->listar($this->vendedor)));
        $this->assertSame(collect([$meu->id, $doColega->id])->sort()->values()->all(), $this->ids($this->listar($this->gestor)));
    }

    public function test_lead_na_lixeira_nao_aparece(): void
    {
        $this->lead($this->tenant, $this->vendedor)->delete();

        $this->listar($this->vendedor)->assertJsonPath('total', 0);
    }

    public function test_busca_por_nome_email_descricao_e_telefone(): void
    {
        $maria = $this->lead($this->tenant, $this->vendedor, ['nome' => 'Maria Souza', 'telefone' => '+5511988887777']);
        $this->lead($this->tenant, $this->vendedor, ['nome' => 'João Lima', 'email' => 'joao@firma.com', 'descricao' => 'Veio da feira']);

        $this->assertSame([$maria->id], $this->ids($this->listar($this->vendedor, ['busca' => 'souza'])));
        $this->assertSame([$maria->id], $this->ids($this->listar($this->vendedor, ['busca' => '(11) 98888'])));
        $this->assertCount(1, $this->listar($this->vendedor, ['busca' => 'firma.com'])->json('data'));
        $this->assertCount(1, $this->listar($this->vendedor, ['busca' => 'feira'])->json('data'));
    }

    /** Review Focus 4 */
    public function test_curingas_do_like_sao_texto_literal(): void
    {
        $comPercentual = $this->lead($this->tenant, $this->vendedor, ['nome' => 'Desconto 50% Ltda']);
        $this->lead($this->tenant, $this->vendedor, ['nome' => 'Compra 500 unidades']);
        $this->lead($this->tenant, $this->vendedor, ['nome' => 'Nome_com_sublinhado']);

        $this->assertSame([$comPercentual->id], $this->ids($this->listar($this->vendedor, ['busca' => '50%'])));
        $this->assertCount(1, $this->listar($this->vendedor, ['busca' => 'e_c'])->json('data'));
        $this->listar($this->vendedor, ['busca' => '(11)']);
    }

    public function test_filtro_por_estagio_e_por_situacao(): void
    {
        $funil = Funil::where('is_default', true)->firstOrFail();
        $aberto = Estagio::factory()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);
        $ganho = Estagio::factory()->ganho()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);

        $emAberto = $this->lead($this->tenant, $this->vendedor, ['estagio_id' => $aberto->id]);
        $fechado = $this->lead($this->tenant, $this->vendedor, ['estagio_id' => $ganho->id]);
        $comProjetoAberto = $this->lead($this->tenant, $this->vendedor, ['estagio_id' => $aberto->id]);
        $this->projeto($comProjetoAberto, ['status_id' => Statu::factory()->create(['tenant_id' => $this->tenant->id])->id]);

        $this->assertSame(
            collect([$emAberto->id, $comProjetoAberto->id])->sort()->values()->all(),
            $this->ids($this->listar($this->vendedor, ['estagios' => [$aberto->id]]))
        );
        $this->assertSame([$fechado->id], $this->ids($this->listar($this->vendedor, ['status' => 'arquivado'])));
        $this->assertSame([$comProjetoAberto->id], $this->ids($this->listar($this->vendedor, ['status' => 'aberto'])));
        $this->assertSame(
            collect([$emAberto->id, $fechado->id])->sort()->values()->all(),
            $this->ids($this->listar($this->vendedor, ['status' => 'sem_projeto']))
        );
    }

    public function test_filtro_por_periodo(): void
    {
        $this->travel(-40)->days();
        $this->lead($this->tenant, $this->vendedor);
        $this->travelBack();
        $recente = $this->lead($this->tenant, $this->vendedor);

        $this->assertSame([$recente->id], $this->ids($this->listar($this->vendedor, ['de' => now()->subDays(30)->toDateString()])));
    }

    public function test_periodo_invertido_e_recusado(): void
    {
        $this->actingAs($this->vendedor)
            ->getJson('/api/leads?de=2026-09-10&ate=2026-09-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ate');
    }

    public function test_rota_antiga_nao_existe_mais(): void
    {
        $this->actingAs($this->vendedor)->postJson('/api/pegarUsuarios')->assertNotFound();
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter=ListagemPaginadaTest`
Expected: FAIL com 404 em `GET /api/leads`. A rota não existe; o `/api/leads/{usuario}/atividades` não casa com `/api/leads`.

- [ ] **Step 3: Relação, índice e query object**

Em `app/Models/Usuario.php`:

```php
    public function projetos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Projeto::class, 'usuario_id');
    }
```

`database/migrations/2026_09_28_140001_indice_de_criacao_em_usuarios.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A listagem ordena e filtra por data de criação dentro do tenant. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', fn (Blueprint $t) => $t->index(['tenant_id', 'created_at']));
    }

    public function down(): void
    {
        Schema::table('usuarios', fn (Blueprint $t) => $t->dropIndex(['tenant_id', 'created_at']));
    }
};
```

`app/Queries/Leads/ListagemDeLeads.php`:

```php
<?php

namespace App\Queries\Leads;

use App\Models\Projeto;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * A lista de leads do Dashboard: visibilidade, filtros, ordem e paginação,
 * todos no banco.
 *
 * Os filtros são os mesmos que Dashboard.vue aplicava no navegador sobre a
 * carteira inteira (busca, estágios, período, situação), com a mesma
 * semântica — só mudou onde rodam.
 */
class ListagemDeLeads
{
    public const POR_PAGINA = 25;
    public const MAX_POR_PAGINA = 100;

    /**
     * @param  array{busca?:string, estagios?:list<int>, de?:string, ate?:string, status?:string, per_page?:int}  $filtros
     */
    public function __invoke(User $user, array $filtros): LengthAwarePaginator
    {
        $porPagina = min(max((int) ($filtros['per_page'] ?? self::POR_PAGINA), 1), self::MAX_POR_PAGINA);

        return Usuario::visibleTo($user)
            ->with('estagio')
            ->addSelect([
                'usuarios.*',
                'tem_projeto' => Projeto::whereColumn('usuario_id', 'usuarios.id')->selectRaw('COUNT(*) > 0'),
                'tem_projeto_aberto' => Projeto::whereColumn('usuario_id', 'usuarios.id')
                    ->whereHas('status', fn ($q) => $q->open())
                    ->selectRaw('COUNT(*) > 0'),
            ])
            ->when($filtros['busca'] ?? null, fn (Builder $q, string $busca) => $this->buscar($q, $busca))
            ->when($filtros['estagios'] ?? null, fn (Builder $q, array $ids) => $q->whereIn('usuarios.estagio_id', $ids))
            ->when($filtros['de'] ?? null, fn (Builder $q, string $de) => $q->where('usuarios.created_at', '>=', Carbon::parse($de)->startOfDay()))
            ->when($filtros['ate'] ?? null, fn (Builder $q, string $ate) => $q->where('usuarios.created_at', '<=', Carbon::parse($ate)->endOfDay()))
            ->when($filtros['status'] ?? null, fn (Builder $q, string $status) => $this->situacao($q, $status))
            ->orderByDesc('usuarios.created_at')
            ->orderByDesc('usuarios.id')
            ->paginate($porPagina)
            ->withQueryString();
    }

    private function buscar(Builder $q, string $busca): void
    {
        // Review Focus 4: % e _ do usuário são texto, não curinga.
        $termo = '%'.addcslashes($busca, '%_\\').'%';
        $digitos = preg_replace('/\D/', '', $busca);

        $q->where(function (Builder $w) use ($termo, $digitos) {
            $w->where('usuarios.nome', 'like', $termo)
                ->orWhere('usuarios.email', 'like', $termo)
                ->orWhere('usuarios.descricao', 'like', $termo);

            // Telefone é E.164 no banco: "(11) 98888" vira "1198888".
            if (strlen($digitos) >= 3) {
                $w->orWhere('usuarios.telefone', 'like', '%'.$digitos.'%');
            }
        });
    }

    private function situacao(Builder $q, string $status): void
    {
        match ($status) {
            'arquivado' => $q->whereHas('estagio', fn ($e) => $e->fechado()),
            'aberto' => $q->whereHas('projetos', fn ($p) => $p->whereHas('status', fn ($s) => $s->open())),
            'sem_projeto' => $q->whereDoesntHave('projetos'),
            default => null,
        };
    }
}
```

`app/Http/Requests/ListagemDeLeadsRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Só valida a FORMA dos filtros. Quem decide o que cada agente enxerga é
 * Usuario::scopeVisibleTo, dentro de ListagemDeLeads — por isso authorize()
 * é true: não há recurso de terceiro identificado na requisição.
 */
class ListagemDeLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'busca' => ['nullable', 'string', 'max:100'],
            'estagios' => ['nullable', 'array'],
            'estagios.*' => ['integer'],
            'de' => ['nullable', 'date'],
            'ate' => ['nullable', 'date', 'after_or_equal:de'],
            'status' => ['nullable', 'in:todos,arquivado,aberto,sem_projeto'],
            'page' => ['nullable', 'integer', 'min:1'],
            // Sem `max`: acima do teto é limitado, não recusado.
            'per_page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return ['ate.after_or_equal' => 'A data final precisa ser igual ou posterior à inicial.'];
    }
}
```

- [ ] **Step 4: Controller e rota**

Em `app/Http/Controllers/Userarios.php`, apague o método `view()` e adicione:

```php
    public function index(\App\Http\Requests\ListagemDeLeadsRequest $request, \App\Queries\Leads\ListagemDeLeads $listagem)
    {
        return response()->json($listagem($request->user(), $request->validated()));
    }
```

Em `routes/api.php`, troque `Route::post('/pegarUsuarios', [Userarios::class, 'view']);` por:

```php
    Route::get('/leads', [Userarios::class, 'index']);
```

Em `TodaRotaDeEscritaAutorizaTest::ISENTAS`, remova `'POST api/pegarUsuarios'` e o comentário "Leitura disfarçada de POST por convenção antiga…".

Atualize os consumidores nos testes:
- `TenantIsolationTest:31`: `postJson('/api/pegarUsuarios')` → `getJson('/api/leads')`, e onde a resposta é lida como lista (`->json()`), passe a ler `->json('data')`.
- `VisibilidadeDeLeadsTest:54,63`: a mesma troca.
- `LixeiraDeLeadTest`: `collect(...postJson('/api/pegarUsuarios')->json())` → `collect(...getJson('/api/leads')->json('data'))`.

- [ ] **Step 5: Dashboard consome a página do servidor**

Em `resources/js/Pages/Dashboard.vue`, no `<script setup>`:
- apague `filteredUsuarios` (linhas 119-141);
- mantenha `dateFromComputed`/`dateToComputed`, que agora só servem para montar os parâmetros;
- substitua `buscarUsuarios` (143-160) por:

```js
    const paginacao = ref({ pagina: 1, ultimaPagina: 1, total: 0 });

    const dataISO = (d) => d ? d.toISOString().slice(0, 10) : undefined;

    // Os filtros que antes rodavam no navegador sobre a carteira inteira agora
    // viram parâmetros da consulta (ListagemDeLeads, no servidor).
    const parametrosDaLista = (pagina) => ({
        page: pagina,
        per_page: 25,
        busca: search.value?.trim() || undefined,
        estagios: filterEstagios.value.length ? filterEstagios.value : undefined,
        de: dataISO(dateFromComputed.value),
        ate: dataISO(dateToComputed.value),
        status: filterStatus.value !== 'todos' ? filterStatus.value : undefined,
    });

    const buscarUsuarios = async (pagina = 1) => {
        isLoading.value = true;
        try {
            const { data } = await axios.get('/api/leads', { params: parametrosDaLista(pagina) });
            usuarios.value = data.data;
            paginacao.value = { pagina: data.current_page, ultimaPagina: data.last_page, total: data.total };
        } catch (error) {
            console.error('Erro ao buscar leads:', error);
        } finally {
            isLoading.value = false;
        }
    };

    // Filtro mudou: volta para a primeira página. Busca por texto espera o
    // usuário parar de digitar (300 ms) para não disparar uma consulta por tecla.
    let atrasoDaBusca = null;
    watch(search, () => {
        clearTimeout(atrasoDaBusca);
        atrasoDaBusca = setTimeout(() => buscarUsuarios(1), 300);
    });
    watch([filterEstagios, filterStatus, filterDatePreset, filterDateFrom, filterDateTo], () => buscarUsuarios(1), { deep: true });
```

Adicione `watch` ao `import { … } from 'vue'`. Em `addUsuario`, remova `user_id: user.value.id` do payload.

No template:
- `v-for="usuario in filteredUsuarios"` → `v-for="usuario in usuarios"`;
- linhas 435 e 517: `filteredUsuarios.length` → `paginacao.total`;
- linha 381 (fallback do card quando as métricas falham): `usuarios.length` → `paginacao.total`;
- no empty state (536-550), a condição "nenhum lead cadastrado" passa a ser `paginacao.total === 0 && activeFiltersCount === 0 && !search`, e o "nenhum resultado para os filtros" cobre o resto;
- logo depois da tabela (depois da linha 590), adicione:

```vue
                <div v-if="paginacao.ultimaPagina > 1" class="db-paginacao">
                    <button class="db-btn-ghost" :disabled="paginacao.pagina <= 1 || isLoading" @click="buscarUsuarios(paginacao.pagina - 1)">Anterior</button>
                    <span>Página {{ paginacao.pagina }} de {{ paginacao.ultimaPagina }} · {{ paginacao.total }} leads</span>
                    <button class="db-btn-ghost" :disabled="paginacao.pagina >= paginacao.ultimaPagina || isLoading" @click="buscarUsuarios(paginacao.pagina + 1)">Próxima</button>
                </div>
```

Use o prefixo de classe que o arquivo já usa para botões secundários. Confira no `<style>` do `Dashboard.vue` e troque `db-btn-ghost` se o nome for outro. Adicione `.db-paginacao { display:flex; align-items:center; justify-content:flex-end; gap:0.75rem; margin-top:1rem; font-size:0.8rem; color:var(--t2); }` ao bloco de estilo.

Confira:

```bash
grep -n "filteredUsuarios\|pegarUsuarios" resources/js/Pages/Dashboard.vue
```

Expected: nenhuma ocorrência.

- [ ] **Step 6: Rodar o teste da tarefa, a suíte inteira e o build**

Run: `php artisan test --filter=ListagemPaginadaTest && php artisan test && npm run build`
Expected: PASS.

- [ ] **Step 7: Commit** (pular)

```bash
git add app/Queries app/Http/Requests/ListagemDeLeadsRequest.php database/migrations/2026_09_28_140001_indice_de_criacao_em_usuarios.php app/Models/Usuario.php app/Http/Controllers/Userarios.php routes/api.php resources/js/Pages/Dashboard.vue tests
git commit -m "fix(P7): lista de leads paginada e filtrada no servidor"
```

---

### Task 15: Métricas e tarefas pendentes sem carregar a lista de ids

**Causa raiz:** `metricas()` (`Userarios.php:436`) e `TarefaController::pendentes()` (linha 91) fazem `pluck('id')` de todos os leads visíveis e mandam essa lista num `whereIn`. O número de bindings cresce com a carteira.

**Files:**
- Modify: `app/Http/Controllers/Userarios.php` (`metricas`)
- Modify: `app/Http/Controllers/TarefaController.php` (`pendentes`)
- Test: `tests/Feature/Leads/MetricasSemListaDeIdsTest.php`

**Interfaces:**
- Produces: a mesma resposta JSON de hoje, com as mesmas chaves e os mesmos valores.

- [ ] **Step 1: Escrever o teste de caracterização e o de consulta**

O primeiro teste **passa antes** da mudança. Ele fixa os números atuais, para garantir que a refatoração não altera nada. O segundo falha antes.

```php
<?php

namespace Tests\Feature\Leads;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Statu;
use App\Models\Tarefa;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class MetricasSemListaDeIdsTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    private Tenant $tenant;
    private User $vendedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = $this->novoTenant();
        $this->vendedor = $this->agente($this->tenant);

        $funil = Funil::where('is_default', true)->first() ?? Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
        $aberto = Estagio::factory()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);
        $ganho = Estagio::factory()->ganho()->create(['tenant_id' => $this->tenant->id, 'funil_id' => $funil->id]);
        $statusAberto = Statu::factory()->create(['tenant_id' => $this->tenant->id]);

        $a = $this->lead($this->tenant, $this->vendedor, ['estagio_id' => $aberto->id]);
        $this->lead($this->tenant, $this->vendedor, ['estagio_id' => $aberto->id]);
        $this->lead($this->tenant, $this->vendedor, ['estagio_id' => $ganho->id]);
        $this->projeto($a, ['preco' => 1000, 'status_id' => $statusAberto->id]);

        // Na lixeira: não conta em nada.
        $this->lead($this->tenant, $this->vendedor, ['estagio_id' => $aberto->id])->delete();
        // De um colega: o vendedor não enxerga.
        $this->lead($this->tenant, $this->agente($this->tenant), ['estagio_id' => $aberto->id]);

        Tarefa::create(['usuario_id' => $a->id, 'titulo' => 'Ligar hoje', 'data_limite' => today()]);
        Tarefa::create(['usuario_id' => $a->id, 'titulo' => 'Atrasada', 'data_limite' => today()->subDay()]);
    }

    public function test_numeros_das_metricas(): void
    {
        $this->actingAs($this->vendedor)->getJson('/api/metricas')->assertOk()
            ->assertJsonPath('total_leads', 3)
            ->assertJsonPath('leads_ativos', 2)
            ->assertJsonPath('leads_arquivados', 1)
            ->assertJsonPath('total_com_projeto', 1)
            ->assertJsonPath('valor_projetos_abertos', 1000.0)
            ->assertJsonPath('taxa_conversao', 33.3);
    }

    public function test_tarefas_pendentes(): void
    {
        $this->actingAs($this->vendedor)->getJson('/api/tarefasPendentes')->assertOk()
            ->assertJsonCount(1, 'hoje')
            ->assertJsonCount(1, 'atrasadas');
    }

    public function test_nenhuma_consulta_carrega_a_carteira_como_lista_de_ids(): void
    {
        for ($i = 0; $i < 40; $i++) {
            $this->lead($this->tenant, $this->vendedor);
        }

        $maiorNumeroDeBindings = 0;
        DB::listen(function ($q) use (&$maiorNumeroDeBindings) {
            $maiorNumeroDeBindings = max($maiorNumeroDeBindings, count($q->bindings));
        });

        $this->actingAs($this->vendedor)->getJson('/api/metricas')->assertOk();
        $this->actingAs($this->vendedor)->getJson('/api/tarefasPendentes')->assertOk();

        $this->assertLessThan(10, $maiorNumeroDeBindings, 'Alguma consulta recebeu a lista de ids dos leads como bindings.');
    }
}
```

- [ ] **Step 2: Rodar**

Run: `php artisan test --filter=MetricasSemListaDeIdsTest`
Expected: os dois primeiros **PASS**. Se falharem, os números do fixture estão errados: ajuste o **teste** ao comportamento atual antes de mexer no código. O terceiro **FAIL**, com cerca de 43 bindings.

- [ ] **Step 3: Subconsulta no lugar da lista**

Em `Userarios::metricas`, substitua a primeira linha (`$leadIds = …->pluck('id');`) e os usos de `$leadIds` por:

```php
        // Subconsulta, não lista: o banco resolve "quais leads este agente vê"
        // dentro da própria consulta. pluck('id') + whereIn mandava a carteira
        // inteira como bindings.
        $visiveis = fn () => Usuario::visibleTo(auth()->user())->select('usuarios.id');
```

- `$valorAberto`: `Projeto::whereIn('usuario_id', $visiveis())->whereHas(...)…`
- `$valorFechadoMes`: `Projeto::whereIn('usuario_id', $visiveis())->…`
- `$totalLeads = Usuario::visibleTo(auth()->user())->count();`
- `$leadsComProjeto = Projeto::whereIn('usuario_id', $visiveis())->distinct('usuario_id')->count('usuario_id');`. O ternário com `isNotEmpty()` sai.

Em `TarefaController::pendentes`, troque `$leadIds = …->pluck('id');` e o `if ($leadIds->isEmpty())` por `$visiveis = fn () => Usuario::visibleTo(auth()->user())->select('usuarios.id');`. Nas duas consultas, use `whereIn('usuario_id', $visiveis())`.

- [ ] **Step 4: Rodar o teste da tarefa e a suíte inteira**

Run: `php artisan test --filter=MetricasSemListaDeIdsTest && php artisan test`
Expected: PASS nos três testes da tarefa e na suíte.

- [ ] **Step 5: Commit** (pular)

```bash
git add app/Http/Controllers/Userarios.php app/Http/Controllers/TarefaController.php tests/Feature/Leads/MetricasSemListaDeIdsTest.php
git commit -m "fix(P7): metricas e pendentes por subconsulta, sem lista de ids"
```

---

### Task 16: Timeline no activity log paginado (fase 4 da spec do activity log)

**Causa raiz:**
- `Userarios::timeline()` monta o histórico em tempo de execução, com N+1 nos projetos e sem paginação.
- O endpoint paginado `/api/leads/{usuario}/atividades` já existe, mas ninguém usa. Ele devolve a linha crua do log, num formato que a tela não entende: linhas do backfill só têm `{backfill:true}`, e as mudanças de estágio guardam ids, não nomes.
- O teste de paridade compara só os tipos.

**Files:**
- Create: `app/Http/Resources/AtividadeResource.php`
- Modify: `app/Http/Controllers/LeadAtividadeController.php`
- Modify: `app/Observers/UsuarioObserver.php` (grava os nomes dos estágios)
- Modify: `resources/js/Pages/Usuario/TimelinePanel.vue` (script: linhas 1-83; template: botão "carregar mais")
- Modify: `app/Http/Controllers/Userarios.php` (`timeline()` com docblock `@deprecated`), `routes/api.php:72-73` (comentário)
- Modify: `tests/Feature/ActivityLog/TimelineParityTest.php`
- Test: `tests/Feature/ActivityLog/TimelinePaginadaTest.php`

**Interfaces:**
- Consumes: eventos `*_removido` e `*_restaurado` (Tarefa 6).
- Produces: `GET /api/leads/{usuario}/atividades?page=&per_page=` responde o JSON do paginator, com `data[]` no formato do `TimelinePanel`: `{id, tipo, data, descricao?, nome?, titulo?, projeto_nome?, estagio_anterior?, estagio_novo?}`. `per_page` vai no máximo a 100.

- [ ] **Step 1: Reforçar o teste de paridade, que deve falhar**

Em `tests/Feature/ActivityLog/TimelineParityTest.php`, adicione `use Illuminate\Support\Arr;` e `use Illuminate\Support\Carbon;`. Troque `tiposDoEndpointNovo()` (que lia `data.*.event`) para ler `data.*.tipo` e acrescente:

```php
    /**
     * O que a tela mostra de cada evento: tipo, instante e os campos que o
     * TimelinePanel lê para aquele tipo. É isto que precisa bater entre o
     * timeline() antigo e o endpoint novo — não só a lista de tipos.
     */
    private function vista(array $evento): array
    {
        $campos = match ($evento['tipo']) {
            'anotacao' => ['descricao'],
            'projeto_anotacao' => ['descricao', 'projeto_nome'],
            'arquivo', 'projeto' => ['nome'],
            'projeto_anexo' => ['nome', 'projeto_nome'],
            'status_alterado' => ['estagio_anterior', 'estagio_novo'],
            'tarefa_criada', 'tarefa_concluida' => ['titulo'],
            default => [],
        };

        return ['tipo' => $evento['tipo'], 'instante' => Carbon::parse($evento['data'])->timestamp]
            + Arr::only($evento, $campos);
    }

    private function ordenar(array $vistas): array
    {
        usort($vistas, fn ($a, $b) => [$a['tipo'], $a['instante']] <=> [$b['tipo'], $b['instante']]);

        return $vistas;
    }

    public function test_os_dois_caminhos_mostram_o_mesmo_conteudo_e_a_mesma_data(): void
    {
        $antigo = $this->actingAs($this->staff)->getJson("/api/timeline/{$this->lead->id}")->json();
        $novo = $this->actingAs($this->staff)->getJson("/api/leads/{$this->lead->id}/atividades")->json('data');

        $this->assertSame(
            $this->ordenar(array_map(fn ($e) => $this->vista($e), $antigo)),
            $this->ordenar(array_map(fn ($e) => $this->vista($e), $novo)),
        );
    }
```

Em `test_endpoint_e_paginado_e_ordenado_do_mais_recente_para_o_mais_antigo`, troque `pluck('created_at')` por `pluck('data')`.

`tests/Feature/ActivityLog/TimelinePaginadaTest.php`:

```php
<?php

namespace Tests\Feature\ActivityLog;

use App\Models\Anotacao;
use App\Models\Estagio;
use App\Models\Funil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CenarioDeTenant;
use Tests\TestCase;

class TimelinePaginadaTest extends TestCase
{
    use RefreshDatabase, CenarioDeTenant;

    public function test_mais_de_uma_pagina_e_per_page_com_teto(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);

        for ($i = 0; $i < 25; $i++) {
            Anotacao::create(['descricao' => "Nota {$i}", 'usuario_id' => $lead->id]);
        }

        $pagina1 = $this->actingAs($dono)->getJson("/api/leads/{$lead->id}/atividades")->assertOk();
        $pagina1->assertJsonCount(20, 'data')->assertJsonPath('last_page', 2);

        // 25 anotações + lead_criado = 26 eventos.
        $this->actingAs($dono)->getJson("/api/leads/{$lead->id}/atividades?page=2")->assertJsonCount(6, 'data');
        $this->actingAs($dono)->getJson("/api/leads/{$lead->id}/atividades?per_page=100000")->assertJsonPath('per_page', 100);
    }

    public function test_mudanca_de_estagio_chega_com_nomes_e_nao_com_ids(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $funil = Funil::where('is_default', true)->first() ?? Funil::factory()->padrao()->create(['tenant_id' => $tenant->id]);
        $novo = Estagio::factory()->create(['tenant_id' => $tenant->id, 'funil_id' => $funil->id, 'descricao' => 'Qualificado']);
        $lead = $this->lead($tenant, $dono);

        $lead->update(['estagio_id' => $novo->id]);

        $evento = collect($this->actingAs($dono)->getJson("/api/leads/{$lead->id}/atividades")->json('data'))
            ->firstWhere('tipo', 'status_alterado');

        $this->assertSame('Qualificado', $evento['estagio_novo']);
        $this->assertSame('—', $evento['estagio_anterior']);
    }

    public function test_remocao_e_restauracao_aparecem_na_timeline(): void
    {
        $tenant = $this->novoTenant();
        $dono = $this->agente($tenant);
        $lead = $this->lead($tenant, $dono);
        $projeto = $this->projeto($lead, ['nome' => 'Site novo']);

        $projeto->delete();

        $tipos = collect($this->actingAs($dono)->getJson("/api/leads/{$lead->id}/atividades")->json('data'))->pluck('tipo');
        $this->assertContains('projeto_removido', $tipos);
    }
}
```

- [ ] **Step 2: Rodar e confirmar que falha**

Run: `php artisan test --filter='TimelineParityTest|TimelinePaginadaTest'`
Expected: FAIL. `data.*.tipo` não existe (hoje o campo é `event`), faltam os campos de exibição e o `per_page` não tem teto.

- [ ] **Step 3: O Resource**

`app/Http/Resources/AtividadeResource.php`:

```php
<?php

namespace App\Http\Resources;

use App\Models\EstagioHistorico;
use App\Models\ProjetoAnexo;
use App\Models\ProjetoAnotacao;
use App\Models\Tarefa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Uma linha do activity log no formato que o TimelinePanel lê.
 *
 * Os campos saem primeiro de `properties` (gravados pelo observer no
 * momento do evento) e, na falta, do `subject` — que é o caso das linhas do
 * backfill, cujas properties são só {backfill: true}. Nomes de estágio vêm do
 * mapa que o controller carrega em lote, nunca uma consulta por linha.
 *
 * Subject na lixeira não é carregado (config activitylog
 * subject_returns_soft_deleted_models = false): o evento aparece com o
 * rótulo do tipo e sem o nome, o que é aceitável para registro antigo.
 */
class AtividadeResource extends JsonResource
{
    private const COM_DESCRICAO = ['anotacao', 'projeto_anotacao'];
    private const DE_ESTAGIO = ['status_alterado', 'funil_alterado'];

    /** @param  array<int,string>  $nomesDeEstagio  id => descrição, withTrashed */
    public function __construct($resource, private readonly array $nomesDeEstagio = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $propriedades = collect($this->properties);
        $subject = $this->subject;
        $tipo = $this->event;

        $campos = [
            'id' => $this->id,
            'tipo' => $tipo,
            'data' => $this->created_at?->toIso8601String(),
            'nome' => $propriedades->get('nome') ?? $subject?->getAttribute('nome'),
            'titulo' => $propriedades->get('titulo') ?? ($subject instanceof Tarefa ? $subject->titulo : null),
            'projeto_nome' => $propriedades->get('projeto_nome') ?? $this->projetoDoSubject($subject),
        ];

        if (in_array($tipo, self::COM_DESCRICAO, true)) {
            $campos['descricao'] = $propriedades->get('descricao') ?? $subject?->getAttribute('descricao');
        }

        if (in_array($tipo, self::DE_ESTAGIO, true)) {
            $campos['estagio_anterior'] = $this->nomeDoEstagio($propriedades, $subject, 'anterior');
            $campos['estagio_novo'] = $this->nomeDoEstagio($propriedades, $subject, 'novo');
        }

        return array_filter($campos, fn ($valor) => $valor !== null);
    }

    private function projetoDoSubject($subject): ?string
    {
        return $subject instanceof ProjetoAnotacao || $subject instanceof ProjetoAnexo
            ? $subject->projeto?->nome
            : null;
    }

    /**
     * Três gerações de linha: com o nome gravado (observer a partir desta
     * tarefa), com o id em `estagio_{lado}_id` (observer anterior) ou
     * `tag_id_{lado}` (antes do rename tags -> estagios), e do backfill, em
     * que o id está no próprio EstagioHistorico do subject.
     */
    public static function idsDeEstagio($atividade): array
    {
        $p = collect($atividade->properties);
        $s = $atividade->subject;

        return array_filter([
            $p->get('estagio_anterior_id') ?? $p->get('tag_id_anterior') ?? ($s instanceof EstagioHistorico ? $s->estagio_anterior_id : null),
            $p->get('estagio_novo_id') ?? $p->get('tag_id_novo') ?? ($s instanceof EstagioHistorico ? $s->estagio_novo_id : null),
        ]);
    }

    private function nomeDoEstagio($propriedades, $subject, string $lado): string
    {
        if ($nome = $propriedades->get("estagio_{$lado}")) {
            return $nome;
        }

        $id = $propriedades->get("estagio_{$lado}_id")
            ?? $propriedades->get("tag_id_{$lado}")
            ?? ($subject instanceof EstagioHistorico ? $subject->{"estagio_{$lado}_id"} : null);

        return $this->nomesDeEstagio[$id] ?? '—';
    }
}
```

Antes de continuar, confira que o backfill usa `EstagioHistorico` como subject de `status_alterado`/`funil_alterado`:

```bash
grep -n "EstagioHistorico::class" app/Console/Commands/BackfillLeadActivities.php
```

Se o subject for outro (por exemplo, `Usuario`), ajuste `idsDeEstagio` e `nomeDoEstagio` para ler os ids dele. O teste de paridade vai acusar se estiver errado.

- [ ] **Step 4: O controller usa o Resource**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Resources\AtividadeResource;
use App\Models\Activity;
use App\Models\Estagio;
use App\Models\ProjetoAnexo;
use App\Models\ProjetoAnotacao;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\Request;

/**
 * Histórico de um lead, servido do activity log, no formato do TimelinePanel.
 *
 * Substitui Userarios::timeline() (deprecado). Ver o teste de paridade em
 * tests/Feature/ActivityLog/TimelineParityTest.php, que compara o conteúdo
 * exibido, não só os tipos.
 */
class LeadAtividadeController extends Controller
{
    private const POR_PAGINA = 20;
    private const MAX_POR_PAGINA = 100;

    public function index(Request $request, Usuario $usuario)
    {
        $this->authorize('view', $usuario);

        $porPagina = min(max($request->integer('per_page') ?: self::POR_PAGINA, 1), self::MAX_POR_PAGINA);

        $pagina = Activity::where('lead_id', $usuario->id)
            ->with(['subject' => fn (MorphTo $morph) => $morph->morphWith([
                ProjetoAnotacao::class => ['projeto'],
                ProjetoAnexo::class => ['projeto'],
            ])])
            ->orderByDesc('created_at')
            // Desempate estável: vários eventos podem cair no mesmo segundo.
            ->orderByDesc('id')
            ->paginate($porPagina);

        $ids = $pagina->getCollection()->flatMap(fn ($a) => AtividadeResource::idsDeEstagio($a))->unique()->all();
        $nomes = $ids ? Estagio::withTrashed()->whereIn('id', $ids)->pluck('descricao', 'id')->all() : [];

        $pagina->through(fn (Activity $a) => (new AtividadeResource($a, $nomes))->resolve($request));

        return response()->json($pagina);
    }
}
```

- [ ] **Step 5: Daqui em diante, o observer grava os nomes**

Em `UsuarioObserver::updated`, adicione `use App\Models\Estagio;` e, no array de propriedades do `LeadActivity::registrar`, acrescente:

```php
                // Nome gravado junto com o id: o histórico mostra o estágio
                // como ele se chamava no momento, mesmo que seja renomeado ou
                // arquivado depois.
                'estagio_anterior' => $estagioAnterior ? Estagio::withTrashed()->whereKey($estagioAnterior)->value('descricao') : null,
                'estagio_novo' => $usuario->estagio_id ? Estagio::withTrashed()->whereKey($usuario->estagio_id)->value('descricao') : null,
```

- [ ] **Step 6: `TimelinePanel.vue` consome o endpoint paginado**

No `<script setup>`, troque o estado e `buscarTimeline` por:

```js
    const eventos     = ref([]);
    const isLoading   = ref(false);
    const filtroAtivo = ref('todos');
    const pagina      = ref(0);
    const ultimaPagina = ref(1);

    const temMais = computed(() => pagina.value < ultimaPagina.value);

    const buscarTimeline = async (proxima = 1) => {
        isLoading.value = true;
        try {
            const { data } = await axios.get(`/api/leads/${props.usuarioId}/atividades`, { params: { page: proxima } });
            eventos.value = proxima === 1 ? data.data : [...eventos.value, ...data.data];
            pagina.value = data.current_page;
            ultimaPagina.value = data.last_page;
        } catch {
            if (proxima === 1) eventos.value = [];
        } finally {
            isLoading.value = false;
        }
    };

    onMounted(() => buscarTimeline(1));
```

Estenda os grupos, a configuração de tipos e os títulos para os eventos que o log tem e o timeline antigo não tinha:

```js
    const gruposProjeto = new Set(['projeto', 'projeto_anotacao', 'projeto_anexo', 'projeto_anotacao_removida', 'projeto_anexo_removido', 'projeto_removido', 'projeto_restaurado']);
    const gruposTarefa  = new Set(['tarefa_criada', 'tarefa_concluida']);
```

No filtro `'status_alterado'` de `eventosFiltrados`, inclua `funil_alterado`:

```js
        if (filtroAtivo.value === 'status_alterado') return eventos.value.filter(e => e.tipo === 'status_alterado' || e.tipo === 'funil_alterado');
```

Acrescente em `tipoConfig`:

```js
        funil_alterado:            { cor: '#f59e0b', bg: 'rgba(245,158,11,0.12)',  label: 'Funil alterado' },
        anotacao_removida:         { cor: '#94a3b8', bg: 'rgba(148,163,184,0.12)', label: 'Anotação removida' },
        arquivo_removido:          { cor: '#94a3b8', bg: 'rgba(148,163,184,0.12)', label: 'Arquivo removido' },
        projeto_anotacao_removida: { cor: '#94a3b8', bg: 'rgba(148,163,184,0.12)', label: 'Anotação de projeto removida' },
        projeto_anexo_removido:    { cor: '#94a3b8', bg: 'rgba(148,163,184,0.12)', label: 'Anexo de projeto removido' },
        projeto_removido:          { cor: '#f06292', bg: 'rgba(240,98,146,0.12)',  label: 'Projeto na lixeira' },
        projeto_restaurado:        { cor: '#a78bfa', bg: 'rgba(167,139,250,0.12)', label: 'Projeto restaurado' },
        lead_removido:             { cor: '#f06292', bg: 'rgba(240,98,146,0.12)',  label: 'Lead na lixeira' },
        lead_restaurado:           { cor: '#94a3b8', bg: 'rgba(148,163,184,0.12)', label: 'Lead restaurado' },
```

Acrescente em `getTitulo`, antes do `default`:

```js
            case 'funil_alterado':            return `Funil: ${evento.estagio_anterior} → ${evento.estagio_novo}`;
            case 'anotacao_removida':         return 'Anotação removida';
            case 'arquivo_removido':          return `Arquivo removido: ${evento.nome ?? ''}`.trim();
            case 'projeto_anotacao_removida': return 'Anotação de projeto removida';
            case 'projeto_anexo_removido':    return `Anexo removido: ${evento.nome ?? ''}`.trim();
            case 'projeto_removido':          return `Projeto na lixeira: "${evento.nome ?? ''}"`;
            case 'projeto_restaurado':        return `Projeto restaurado: "${evento.nome ?? ''}"`;
            case 'lead_removido':             return 'Lead movido para a lixeira';
            case 'lead_restaurado':           return 'Lead restaurado';
```

Nos casos `projeto_anotacao` e `projeto_anexo`, troque `"${evento.projeto_nome}"` por `"${evento.projeto_nome ?? 'projeto'}"`, porque o nome pode faltar em linha antiga.

No template, logo depois da lista de eventos, adicione:

```vue
        <button v-if="temMais" class="tl-mais" :disabled="isLoading" @click="buscarTimeline(pagina + 1)">
            {{ isLoading ? 'Carregando…' : 'Carregar mais' }}
        </button>
```

E no `<style>` do componente: `.tl-mais { display:block; margin:0.75rem auto 0; background:transparent; border:1px solid var(--border); color:var(--t2); border-radius:var(--r-2); padding:0.4rem 0.9rem; font-size:0.78rem; cursor:pointer; }`.

Os filtros continuam aplicados no navegador, sobre o que já foi carregado. Isso é aceitável porque "Todos" é o padrão e o "Carregar mais" traz o resto. Filtrar no servidor fica fora deste plano.

- [ ] **Step 7: Marcar o caminho antigo como deprecado**

Em `Userarios::timeline()`, acima do método:

```php
    /**
     * @deprecated Substituído por LeadAtividadeController::index (activity
     *             log paginado). Mantido só para o teste de paridade; remover
     *             depois que o endpoint novo rodar em produção (spec
     *             2026-09-20-activity-log-design, fase 4).
     */
```

Em `routes/api.php:72-73`, troque o comentário por `// Deprecado: sem consumidor no front desde a fase 4 do activity log. Só o TimelineParityTest usa.`

Confira:

```bash
grep -rn "/api/timeline" resources/js
```

Expected: nenhuma ocorrência.

- [ ] **Step 8: Rodar os testes da tarefa, a suíte inteira e o build**

Run: `php artisan test --filter='TimelineParityTest|TimelinePaginadaTest|LeadActivityObserverTest' && php artisan test && npm run build`
Expected: PASS.

Se `test_os_dois_caminhos_mostram_o_mesmo_conteudo_e_a_mesma_data` falhar **só no instante** de algum tipo, o backfill está gravando uma data diferente da que o timeline antigo usa, por exemplo em `tarefa_concluida` (`concluido_em` em vez de `updated_at`). **Não afrouxe o teste:** corrija a data em `BackfillLeadActivities` e reporte no resumo da tarefa.

- [ ] **Step 9: Commit** (pular)

```bash
git add app/Http/Resources app/Http/Controllers/LeadAtividadeController.php app/Observers/UsuarioObserver.php app/Http/Controllers/Userarios.php routes/api.php resources/js/Pages/Usuario/TimelinePanel.vue tests/Feature/ActivityLog
git commit -m "fix(P7): timeline no activity log paginado, paridade de conteudo"
```

---

### Task 17: Verificação ponta a ponta e fechamento da análise

**Files:**
- Modify: `docs/analise/2026-09-27-problemas-criticos.md` (status de cada P)

- [ ] **Step 1: Suíte, build e debug desligado**

Run: `php artisan test && APP_DEBUG=false php artisan test && npm run build`
Expected: tudo verde, com cerca de 20 arquivos de teste novos em relação à Tarefa 0.

- [ ] **Step 2: Estado do banco de dev**

```bash
php artisan migrate:status | tail -8                       # as seis 2026_09_28_* com "Ran"
mysql -u root -p CRMLeader -e "
SELECT COUNT(*) AS telefones_fora_do_e164 FROM usuarios WHERE telefone IS NOT NULL AND telefone NOT LIKE '+%';
SELECT COUNT(*) AS anexos_fora_do_privado FROM arquivos WHERE local NOT LIKE 'tenants/%';
SELECT COUNT(*) AS anexos_projeto_fora_do_privado FROM projetoAnexos WHERE local NOT LIKE 'tenants/%';"
```

Expected:
- os telefones fora de E.164 são só os listados em `telefones-dry-run.txt`;
- anexos fora do privado: 0, ou só as linhas "sem arquivo" do relatório da Tarefa 4.

- [ ] **Step 3: Smoke test com a aplicação rodando**

Use a skill `run` para subir a aplicação (`composer run dev`) e o Playwright para percorrer, com um usuário de dev:
1. Tela do lead:
   - enviar um PDF, baixar pela tela e ver que o arquivo abre;
   - enviar um `.html` e ver a mensagem "Tipo de arquivo não permitido";
   - o tamanho aparece na lista de anexos.
2. Copiar o link de download e abrir numa janela anônima → vai para o login.
3. Abrir `/storage/arquivos/<qualquer nome antigo>` → 404.
4. Kanban:
   - quick-add com `(11) 99999-8888` → o card aparece e, no perfil, o telefone aparece formatado;
   - quick-add sem telefone → também cadastra.
5. Salvar uma anotação de cerca de 2.000 caracteres → salva inteira. Colar 10.001 → o campo não aceita mais.
6. Excluir um projeto → some da lista. Restaurar com `PATCH /api/projeto/{id}/restaurar` (pelo console do navegador com axios) → volta.
7. Excluir um lead → some do Dashboard e do Kanban. `/leads/{id}` → 404.
8. `/profile` com um vendedor que tem leads → o botão fica desabilitado e o motivo aparece.
9. Dashboard:
   - com mais de 25 leads, a paginação aparece;
   - a busca por nome e por "(11)" funciona;
   - os filtros de estágio, situação e período reduzem o total.
10. Timeline do lead: os eventos aparecem com nomes e o "Carregar mais" traz o restante.

Tire um screenshot de cada tela e guarde em `$SCRATCH/smoke/`.

- [ ] **Step 4: Fechar a análise**

Em `docs/analise/2026-09-27-problemas-criticos.md`, troque a linha **Status** do topo por:

```markdown
**Status:** resolvido em 2026-09-28 pelo plano `docs/superpowers/plans/2026-09-27-correcao-problemas-criticos.md`.
```

Na tabela **Resumo**, acrescente uma coluna "Prova" com o teste que trava cada item:

| # | Prova |
|---|---|
| P1 | `tests/Feature/Arquivos/DownloadProtegidoTest.php`, `GravacaoDeArquivoTest.php`, `CicloDeVidaDoArquivoTest.php` |
| P2 | `tests/Feature/Arquivos/PoliticaDeUploadTest.php` |
| P3 | `tests/Feature/Contas/ExclusaoDeContaTest.php`, `tests/Feature/DataIntegrity/SemCascataDestrutivaTest.php` |
| P4 | `tests/Feature/Exclusao/LixeiraDeLeadTest.php`, `LixeiraDeProjetoTest.php`, `SemCascataDestrutivaTest.php` |
| P5 | `tests/Unit/TelefoneTest.php`, `tests/Feature/Leads/TelefoneDoLeadTest.php`, `NormalizacaoDeTelefonesTest.php` |
| P6 | `tests/Feature/DataIntegrity/LimitesDeTextoTest.php` |
| P7 | `tests/Feature/Leads/ListagemPaginadaTest.php`, `MetricasSemListaDeIdsTest.php`, `tests/Feature/ActivityLog/TimelinePaginadaTest.php`, `TimelineParityTest.php` |
| P8 | `tests/Feature/Funis/PreferenciaDoKanbanTest.php`, `tests/Feature/Navegacao/VerbosDeLeituraTest.php`, `tests/Feature/Auth/LimiteDeRequisicoesTest.php` |

Na seção "Dívida técnica relacionada", marque como resolvidos:
- a pasta duplicada `app/Service` (Tarefa 2);
- o docblock falso (Tarefa 5);
- a fase 4 do activity log (Tarefa 16).

- [ ] **Step 5: Revisar o diff completo**

Run: `git status && git diff --stat`
Expected: só os arquivos listados nas tarefas. Nada é commitado: entregue o resumo ao usuário para ele revisar e commitar.
