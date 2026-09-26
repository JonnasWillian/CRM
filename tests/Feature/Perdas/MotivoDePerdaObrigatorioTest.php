<?php

namespace Tests\Feature\Perdas;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\MotivoPerda;
use App\Models\Perda;
use App\Models\Projeto;
use App\Models\Statu;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A obrigatoriedade vale nos SEIS caminhos que levam ao estado de perda.
 *
 * Uma regra que só cobre o Kanban não é obrigatória — é uma sugestão com cinco
 * desvios, e o relatório "por que perdemos" nasce com buracos que ninguém
 * consegue explicar depois. Cada caminho aqui é testado duas vezes: recusado
 * sem motivo, aceito com motivo e gravando a linha em `perdas`.
 *
 * AO ADICIONAR UM SÉTIMO CAMINHO que possa deixar algo perdido, acrescente-o a
 * `caminhos()`. É a lista que este teste percorre, e é o lugar onde a ausência
 * de cobertura fica visível.
 */
class MotivoDePerdaObrigatorioTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $staff;
    private Funil $funil;
    private Funil $outroFunil;
    private Estagio $aberto;
    private Estagio $perdido;
    private Estagio $perdidoNoOutroFunil;
    private Statu $statusAberto;
    private Statu $statusPerdido;
    private MotivoPerda $motivo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
        app(CurrentTenant::class)->set($this->tenant);

        $this->funil = Funil::factory()->padrao()->create(['tenant_id' => $this->tenant->id]);
        $this->outroFunil = Funil::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->aberto = Estagio::factory()->create([
            'tenant_id' => $this->tenant->id, 'funil_id' => $this->funil->id, 'ordem' => 1,
        ]);
        $this->perdido = Estagio::factory()->perdido()->create([
            'tenant_id' => $this->tenant->id, 'funil_id' => $this->funil->id, 'ordem' => 2,
        ]);
        $this->perdidoNoOutroFunil = Estagio::factory()->perdido()->create([
            'tenant_id' => $this->tenant->id, 'funil_id' => $this->outroFunil->id, 'ordem' => 1,
        ]);

        $this->statusAberto = Statu::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->statusPerdido = Statu::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->statusPerdido->is_lost = true;
        $this->statusPerdido->save();

        $this->motivo = MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function lead(): Usuario
    {
        return Usuario::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->staff->id,
            'funil_id' => $this->funil->id,
            'estagio_id' => $this->aberto->id,
        ]);
    }

    private function projeto(): Projeto
    {
        $projeto = new Projeto();
        $projeto->fill([
            'nome' => 'Projeto de teste',
            'usuario_id' => $this->lead()->id,
            'status_id' => $this->statusAberto->id,
        ]);
        $projeto->tenant_id = $this->tenant->id;
        $projeto->save();

        return $projeto;
    }

    /**
     * Cada caminho devolve [método, url, payload-sem-motivo, status-de-sucesso].
     *
     * @return array<string, callable(): array{0: string, 1: string, 2: array, 3: int}>
     */
    private function caminhos(): array
    {
        return [
            'cadastrar lead já perdido' => fn () => [
                'postJson', '/api/usuarios',
                [
                    'nome' => 'Lead Perdido',
                    'email' => 'lead.perdido@example.com',
                    'telefone' => '11999998888',
                    'funil_id' => $this->funil->id,
                    'estagio_id' => $this->perdido->id,
                ],
                201,
            ],

            'editar lead no perfil' => function () {
                $lead = $this->lead();

                return [
                    'putJson', "/api/usuarios/{$lead->id}",
                    [
                        'nome' => $lead->nome,
                        'email' => $lead->email,
                        'telefone' => '11999998888',
                        'funil_id' => $this->funil->id,
                        'estagio_id' => $this->perdido->id,
                    ],
                    200,
                ];
            },

            'arrastar card no kanban' => function () {
                $lead = $this->lead();

                return [
                    'patchJson', "/api/usuarios/{$lead->id}/estagio",
                    ['estagio_id' => $this->perdido->id],
                    200,
                ];
            },

            'mover de funil para coluna perdida' => function () {
                $lead = $this->lead();

                return [
                    'patchJson', "/api/usuarios/{$lead->id}/funil",
                    ['funil_id' => $this->outroFunil->id, 'estagio_id' => $this->perdidoNoOutroFunil->id],
                    200,
                ];
            },

            'criar projeto já perdido' => fn () => [
                'postJson', '/api/projeto',
                [
                    'nome' => 'Projeto natimorto',
                    'usuario_id' => $this->lead()->id,
                    'status_id' => $this->statusPerdido->id,
                ],
                201,
            ],

            'editar projeto para perdido' => function () {
                $projeto = $this->projeto();

                return [
                    'putJson', "/api/projeto/{$projeto->id}",
                    ['nome' => $projeto->nome, 'status_id' => $this->statusPerdido->id],
                    200,
                ];
            },
        ];
    }

    public function test_nenhum_caminho_aceita_perda_sem_motivo(): void
    {
        foreach ($this->caminhos() as $nome => $montar) {
            [$metodo, $url, $payload] = $montar();

            $resposta = $this->actingAs($this->staff)->{$metodo}($url, $payload);

            $this->assertSame(
                422,
                $resposta->status(),
                "o caminho \"{$nome}\" deixou passar uma perda sem motivo",
            );
            $resposta->assertJsonStructure(['erros' => ['motivo_perda_id']]);
        }

        $this->assertSame(0, Perda::count(), 'nenhuma perda deveria ter sido gravada');
    }

    public function test_todo_caminho_aceita_com_motivo_e_grava_a_perda(): void
    {
        foreach ($this->caminhos() as $nome => $montar) {
            [$metodo, $url, $payload, $sucesso] = $montar();

            $antes = Perda::count();

            $payload['perda'] = [
                'motivo_perda_id' => $this->motivo->id,
                'observacao' => "veio de: {$nome}",
            ];
            // O email precisa ser único por tenant e o caminho de cadastro
            // roda junto dos demais dentro do mesmo teste.
            if (isset($payload['email'])) {
                $payload['email'] = str_replace('@', '+'.$antes.'@', $payload['email']);
            }

            $resposta = $this->actingAs($this->staff)->{$metodo}($url, $payload);

            $this->assertSame(
                $sucesso,
                $resposta->status(),
                "o caminho \"{$nome}\" recusou uma perda com motivo válido: ".$resposta->getContent(),
            );
            $this->assertSame(
                $antes + 1,
                Perda::count(),
                "o caminho \"{$nome}\" não gravou a perda",
            );
        }
    }

    public function test_motivo_de_outro_tenant_nao_serve(): void
    {
        $outroTenant = Tenant::factory()->create();
        $motivoAlheio = MotivoPerda::factory()->create(['tenant_id' => $outroTenant->id]);

        $lead = $this->lead();

        $this->actingAs($this->staff)
            ->patchJson("/api/usuarios/{$lead->id}/estagio", [
                'estagio_id' => $this->perdido->id,
                'perda' => ['motivo_perda_id' => $motivoAlheio->id],
            ])
            ->assertStatus(422);

        $this->assertSame(0, Perda::count());
        $this->assertSame($this->aberto->id, $lead->fresh()->estagio_id);
    }

    public function test_motivo_arquivado_nao_serve_para_perda_nova(): void
    {
        $this->motivo->delete();
        MotivoPerda::factory()->create(['tenant_id' => $this->tenant->id]);

        $lead = $this->lead();

        $this->actingAs($this->staff)
            ->patchJson("/api/usuarios/{$lead->id}/estagio", [
                'estagio_id' => $this->perdido->id,
                'perda' => ['motivo_perda_id' => $this->motivo->id],
            ])
            ->assertStatus(422);

        $this->assertSame(0, Perda::count());
    }

    /**
     * Mexer num lead que JÁ está perdido não é uma perda nova. Sem isto, editar
     * o telefone de um lead perdido pediria o motivo de novo e contaria duas
     * perdas para o mesmo negócio.
     */
    public function test_editar_lead_ja_perdido_nao_pede_motivo_de_novo(): void
    {
        $lead = $this->lead();

        $this->actingAs($this->staff)
            ->patchJson("/api/usuarios/{$lead->id}/estagio", [
                'estagio_id' => $this->perdido->id,
                'perda' => ['motivo_perda_id' => $this->motivo->id],
            ])
            ->assertOk();

        $this->assertSame(1, Perda::count());

        $this->actingAs($this->staff)
            ->putJson("/api/usuarios/{$lead->id}", [
                'nome' => 'Nome Editado',
                'email' => $lead->email,
                'telefone' => '11888887777',
                'funil_id' => $this->funil->id,
                'estagio_id' => $this->perdido->id,
            ])
            ->assertOk();

        $this->assertSame(1, Perda::count(), 'editar um lead já perdido criou uma segunda perda');
    }

    /**
     * Reabrir e perder de novo é uma segunda perda. É a razão de `perdas` ser
     * tabela de eventos e não coluna na entidade.
     */
    public function test_reabrir_e_perder_de_novo_gera_duas_perdas(): void
    {
        $lead = $this->lead();
        $perder = fn () => $this->actingAs($this->staff)->patchJson("/api/usuarios/{$lead->id}/estagio", [
            'estagio_id' => $this->perdido->id,
            'perda' => ['motivo_perda_id' => $this->motivo->id],
        ]);

        $perder()->assertOk();

        $this->actingAs($this->staff)
            ->patchJson("/api/usuarios/{$lead->id}/estagio", ['estagio_id' => $this->aberto->id])
            ->assertOk();

        $perder()->assertOk();

        $this->assertSame(2, Perda::count());
    }

    public function test_perda_de_lead_guarda_o_valor_dos_projetos_em_aberto(): void
    {
        $lead = $this->lead();

        foreach ([1500.00, 2500.00] as $preco) {
            $projeto = new Projeto();
            $projeto->fill(['nome' => 'Proposta aberta', 'usuario_id' => $lead->id, 'status_id' => $this->statusAberto->id, 'preco' => $preco]);
            $projeto->tenant_id = $this->tenant->id;
            $projeto->save();
        }

        $this->actingAs($this->staff)
            ->patchJson("/api/usuarios/{$lead->id}/estagio", [
                'estagio_id' => $this->perdido->id,
                'perda' => ['motivo_perda_id' => $this->motivo->id],
            ])
            ->assertOk();

        $this->assertEquals(4000.00, Perda::first()->valor);
    }
}
