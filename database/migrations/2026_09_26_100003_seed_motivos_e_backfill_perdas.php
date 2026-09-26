<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Semeia o catálogo em todo tenant e registra as perdas que já existiam.
 *
 * ── Por que TODO tenant, inclusive os vazios ──
 *
 * A migração de funis pulou tenants sem estágios, deixando o TenantBootstrapper
 * cuidar dos novos. Aqui não dá: a partir desta entrega, perder exige escolher
 * um motivo. Um tenant sem catálogo não teria o que escolher, e a primeira
 * tentativa de marcar um lead como perdido falharia sem saída — o usuário não
 * tem como cadastrar um motivo no meio do fluxo. O catálogo tem de existir
 * antes da regra valer.
 *
 * ── "Não informado" ──
 *
 * Leads e projetos já perdidos não têm motivo e não há como inventar um. Eles
 * recebem `Não informado`, e o relatório passa a mostrar o próprio ponto cego:
 * "40% das perdas: Não informado" é uma informação verdadeira e acionável
 * (registre daqui pra frente), enquanto uma base que começa vazia sugere
 * falsamente que nunca se perdeu nada antes de hoje.
 *
 * ── Data da perda ──
 *
 * Para leads, `estagio_historicos` guarda quando a transição aconteceu, então a
 * data é a real. Para projetos não existe histórico equivalente, e `updated_at`
 * é o melhor disponível — é uma aproximação, e está marcada como tal.
 *
 * ── Valor ──
 *
 * Projeto tem `preco` e ele continua lá. Lead teria de ser a soma dos projetos
 * abertos no dia da perda, que ninguém guardou; fica nulo, porque um número
 * inventado contamina o relatório de forma silenciosa e permanente.
 */
return new class extends Migration
{
    /**
     * Conjunto inicial. É ponto de partida, não regra: a tela de configuração
     * existe para o tenant renomear, reordenar, arquivar e criar os seus.
     */
    private const MOTIVOS = [
        'Preço',
        'Escolheu concorrente',
        'Sem orçamento',
        'Sem resposta do contato',
        'Fora do perfil',
        'Timing / adiado',
        'Não informado',
    ];

    public function up(): void
    {
        $agora = now();

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            $idsPorDescricao = [];

            foreach (self::MOTIVOS as $posicao => $descricao) {
                $idsPorDescricao[$descricao] = DB::table('motivos_perda')->insertGetId([
                    'tenant_id' => $tenantId,
                    'descricao' => $descricao,
                    'ordem' => $posicao + 1,
                    'created_at' => $agora,
                    'updated_at' => $agora,
                ]);
            }

            $naoInformado = $idsPorDescricao['Não informado'];

            $this->registrarLeadsJaPerdidos($tenantId, $naoInformado);
            $this->registrarProjetosJaPerdidos($tenantId, $naoInformado);
        }
    }

    /**
     * A data vem da última transição para um estágio perdido, quando o
     * histórico a tem; senão, de `updated_at`.
     */
    private function registrarLeadsJaPerdidos(int $tenantId, int $motivoId): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO perdas (tenant_id, perdivel_type, perdivel_id, motivo_perda_id, observacao, valor, user_id, created_at)
            SELECT
                u.tenant_id,
                'App\\Models\\Usuario',
                u.id,
                ?,
                NULL,
                NULL,
                NULL,
                COALESCE(
                    (SELECT MAX(h.created_at)
                       FROM estagio_historicos h
                       JOIN estagios he ON he.id = h.estagio_novo_id
                      WHERE h.usuario_id = u.id
                        AND he.tipo = 'perdido'),
                    u.updated_at,
                    NOW()
                )
              FROM usuarios u
              JOIN estagios e ON e.id = u.estagio_id
             WHERE u.tenant_id = ?
               AND e.tipo = 'perdido'
        SQL, [$motivoId, $tenantId]);
    }

    private function registrarProjetosJaPerdidos(int $tenantId, int $motivoId): void
    {
        DB::statement(<<<'SQL'
            INSERT INTO perdas (tenant_id, perdivel_type, perdivel_id, motivo_perda_id, observacao, valor, user_id, created_at)
            SELECT
                p.tenant_id,
                'App\\Models\\Projeto',
                p.id,
                ?,
                NULL,
                p.preco,
                NULL,
                COALESCE(p.updated_at, NOW())
              FROM projetos p
              JOIN status s ON s.id = p.status_id
             WHERE p.tenant_id = ?
               AND s.is_lost = 1
        SQL, [$motivoId, $tenantId]);
    }

    public function down(): void
    {
        // perdas antes de motivos_perda: a FK é restrict.
        DB::table('perdas')->delete();
        DB::table('motivos_perda')->delete();
    }
};
