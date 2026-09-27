<?php

namespace Tests\Feature\Autorizacao;

use App\Http\Requests\ArquivoRequest;
use App\Http\Requests\ProjetoAnexoRequest;
use App\Http\Requests\ProjetoRequest;
use App\Models\Projeto;
use App\Models\Statu;
use App\Models\Tenant;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Item 7 da rodada B: `ArquivoRequest`, `ProjetoAnexoRequest` e `ProjetoRequest`
 * usam `$this->integer('usuario_id')` em `authorize()` para evitar que um
 * `usuario_id` enviado como ARRAY vire `findMany()` (que devolve uma
 * Collection, nunca null, e furaria a guarda `$model !== null`).
 *
 * `integer()` evita isso, mas por acidente: `(int) [qualquer coisa não vazia]`
 * é sempre `1` em PHP, não importa o que o array carregue. `authorize()` passa
 * a decidir sobre "o recurso de id 1", que pode não ter nada a ver com o que
 * foi enviado. Hoje isso nega por acaso — porque o id 1 tipicamente não é o
 * recurso do ator — mas nada GARANTE essa negativa: se o id 1 calhar de ser um
 * recurso do próprio ator, `authorize()` autoriza sobre um alvo diferente do
 * que o array continha.
 *
 * Sem tocar em `authorize()` (a próxima camada), este teste prova a garantia
 * que `rules()` agora fecha: independente do que `authorize()` decidiu, um
 * `usuario_id` em formato de array NUNCA passa da validação.
 *
 * Os arrays usados aqui carregam ids REAIS (de um lead e de um projeto que
 * de fato existem no tenant ativo), de propósito: `Rule::exists(...)` aceita
 * array e faz um `whereIn`, então um array de ids VÁLIDOS já passava nessa
 * regra antes desta correção — só `required`+`exists` não fechava a classe.
 * Testar com ids reais isola exatamente o que `integer` muda; testar com ids
 * inventados provaria só que `exists` funciona, o que já era sabido.
 *
 * Item 8 da rodada C: nos dois casos com upload (`ArquivoRequest` e
 * `ProjetoAnexoRequest`), o campo `arquivo` era enviado como STRING —
 * `'irrelevante-para-este-teste'` — que já falha a regra `file` sozinha,
 * independente do que `usuario_id` fizer. Isso esvaziava o primeiro
 * `assertTrue($validador->fails())`: ele seria `true` mesmo que a correção
 * de `usuario_id` nunca tivesse existido. Quem carregava o teste de verdade
 * era só o `assertArrayHasKey('usuario_id', ...)` seguinte. Por isso o
 * `arquivo` enviado agora é um upload de verdade (`UploadedFile::fake()`,
 * que satisfaz `required`+`file`) — a falha fica isolada em `usuario_id`, e
 * as duas asserções passam a medir o que o teste promete.
 */
class GuardaDeArrayNoUsuarioIdTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $lead;
    private Projeto $projeto;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();

        // As três regras chamam `app(CurrentTenant::class)->id()` para montar
        // o `Rule::exists(...)->where('tenant_id', ...)` — precisa de um
        // tenant ativo só para `rules()` não estourar `RuntimeException`.
        app(CurrentTenant::class)->set($tenant);

        $this->lead = Usuario::factory()->create(['tenant_id' => $tenant->id]);
        $status = Statu::factory()->create(['tenant_id' => $tenant->id]);
        $this->projeto = Projeto::factory()->create([
            'tenant_id' => $tenant->id, 'usuario_id' => $this->lead->id, 'status_id' => $status->id,
        ]);
    }

    public function test_arquivo_request_rejeita_usuario_id_em_formato_de_array(): void
    {
        $this->assertUsuarioIdArrayRejeitado(new ArquivoRequest(), $this->lead->id, [], [
            'arquivo' => UploadedFile::fake()->create('arquivo.txt', 10),
        ]);
    }

    public function test_projeto_anexo_request_rejeita_usuario_id_em_formato_de_array(): void
    {
        $this->assertUsuarioIdArrayRejeitado(new ProjetoAnexoRequest(), $this->projeto->id, [], [
            'arquivo' => UploadedFile::fake()->create('arquivo.txt', 10),
        ]);
    }

    /** `ProjetoRequest::storeRules()` — sem model na rota, é o caminho de criação. */
    public function test_projeto_request_rejeita_usuario_id_em_formato_de_array_na_criacao(): void
    {
        $this->assertUsuarioIdArrayRejeitado(new ProjetoRequest(), $this->lead->id, [
            'nome' => 'Proposta',
            'status_id' => $this->projeto->status_id,
        ]);
    }

    private function assertUsuarioIdArrayRejeitado(
        ArquivoRequest|ProjetoAnexoRequest|ProjetoRequest $formRequest,
        int $idReal,
        array $outrosCampos,
        array $arquivos = [],
    ): void {
        // setMethod('POST'): as três classes decidem `rules()` (no caso de
        // ProjetoRequest, entre storeRules/updateRules) a partir do verbo, e
        // POST é o caminho de criação em todas.
        $formRequest->setMethod('POST');
        // O array carrega um id REAL e válido — ver o porquê no docblock da
        // classe. Se `integer` não estiver na regra, isto passa hoje.
        $formRequest->merge(['usuario_id' => [$idReal], ...$outrosCampos]);

        // Upload de verdade, não string: precisa passar na regra `file` para
        // que a falha da validação fique isolada em `usuario_id` — ver o
        // porquê no docblock da classe (item 8 da rodada C).
        foreach ($arquivos as $campo => $arquivo) {
            $formRequest->files->set($campo, $arquivo);
        }

        $validador = Validator::make($formRequest->all(), $formRequest->rules(), $formRequest->messages());

        $this->assertTrue(
            $validador->fails(),
            'usuario_id como array deveria falhar a validacao, independente do que authorize() decidiu',
        );
        $this->assertArrayHasKey(
            'usuario_id',
            $validador->errors()->toArray(),
            'a falha precisa apontar para o campo usuario_id',
        );
        $this->assertArrayNotHasKey(
            'arquivo',
            $validador->errors()->toArray(),
            'o arquivo enviado precisa ser valido, para isolar a falha em usuario_id',
        );
    }
}
