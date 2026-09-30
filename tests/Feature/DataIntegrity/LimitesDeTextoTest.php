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

        // TrimStrings (middleware global do Laravel, anterior a esta tarefa)
        // tira espaço das PONTAS de todo input de texto — o fim de $texto tem
        // um espaço e por isso é cortado antes da validação. Não é o que este
        // teste verifica: o que importa é que o MEIO do texto, 2000+
        // caracteres com acento, chega inteiro, sem cortar em VARCHAR(255).
        $this->assertSame(trim($texto), Anotacao::firstOrFail()->descricao);
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

    /**
     * Sem mensagem própria, o `max` cai no arquivo de idioma — com
     * APP_LOCALE=en o usuário leria inglês. Força o en para provar que a
     * mensagem vem do ProjetoRequest.
     */
    public function test_limite_do_projeto_responde_em_portugues_em_qualquer_locale(): void
    {
        app()->setLocale('en');

        $resposta = $this->actingAs($this->dono)->putJson("/api/projeto/{$this->projeto->id}", [
            'nome' => str_repeat('n', LimitesDeTexto::NOME + 1),
            'status_id' => $this->projeto->status_id,
            'descricao' => str_repeat('a', LimitesDeTexto::DESCRICAO + 1),
        ])->assertUnprocessable();

        $resposta->assertJsonPath('errors.descricao.0', 'A descrição pode ter no máximo '.LimitesDeTexto::DESCRICAO.' caracteres.');
        $resposta->assertJsonPath('errors.nome.0', 'O nome pode ter no máximo '.LimitesDeTexto::NOME.' caracteres.');
    }

    public function test_front_recebe_os_mesmos_limites(): void
    {
        $this->actingAs($this->dono)->get('/dashboard')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('limites.anotacao', LimitesDeTexto::ANOTACAO)
                ->where('limites.descricao', LimitesDeTexto::DESCRICAO));
    }
}
