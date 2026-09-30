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
