<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Leva os dados existentes para o mundo de funis múltiplos.
 *
 * Três movimentos: todo tenant que já tinha estágios ganha um funil "Vendas"
 * padrão com eles dentro; `is_active` vira `tipo`; e cada lead herda o funil do
 * seu estágio.
 *
 * ── A ambiguidade de is_active -> tipo ──
 *
 * `is_active` é booleano e `tipo` tem três valores, então o mapeamento de
 * `false` não é decidível sozinho. No conjunto semeado hoje, `is_active = false`
 * cobre três estágios de naturezas diferentes:
 *
 *   Concluído  -> ganho
 *   Cancelado  -> perdido
 *   Pausado    -> nem um nem outro
 *
 * "Pausado" é o caso que não fecha: um negócio pausado não foi ganho nem
 * perdido, ele continua em aberto — apenas parado. Classificá-lo como perdido
 * inflaria a perda; como ganho, seria falso. Ele vai para `aberto`, que é o que
 * ele de fato é.
 *
 * CONSEQUÊNCIA VISÍVEL: para tenants com leads em "Pausado", `leads_ativos`
 * sobe e `leads_arquivados` desce em relação ao número de ontem. A mudança é
 * intencional e corrige uma classificação que já estava errada; quem discordar
 * ajusta o tipo do estágio na tela de configuração, que existe justamente para
 * isso.
 *
 * O reconhecimento de ganho/perdido é por nome e só vale para este backfill —
 * é uma tentativa única sobre os nomes semeados por TenantBootstrapper. Estágio
 * renomeado pelo tenant não casa e cai em `aberto`, o default seguro: ele
 * aparece no funil e pode ser reclassificado na tela. Nenhum código depois
 * deste arquivo volta a inferir significado a partir de nome.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- 1. Um funil padrão por tenant que já tem estágios ---
        // Tenant sem estágio nenhum (ex.: o tenant 1 "Default" de instalação
        // limpa) não ganha funil aqui: quem o cria é o TenantBootstrapper, no
        // registro. Criar um funil vazio agora só deixaria lixo.
        foreach (DB::table('estagios')->distinct()->pluck('tenant_id') as $tenantId) {
            $funilId = DB::table('funis')->insertGetId([
                'tenant_id' => $tenantId,
                'nome' => 'Vendas',
                'descricao' => 'Funil criado na migração, a partir dos estágios que já existiam.',
                'ordem' => 1,
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Sem filtro de deleted_at de propósito: estágio arquivado que
            // ainda segura lead continua aparecendo no Kanban, e sem funil ele
            // sumiria do quadro levando o lead junto.
            DB::table('estagios')->where('tenant_id', $tenantId)->update(['funil_id' => $funilId]);
        }

        // --- 2. is_active -> tipo ---
        DB::table('estagios')->where('is_active', true)->update(['tipo' => 'aberto']);

        DB::table('estagios')
            ->where('is_active', false)
            ->whereRaw("LOWER(descricao) IN ('concluído', 'concluido', 'ganho', 'fechado')")
            ->update(['tipo' => 'ganho']);

        DB::table('estagios')
            ->where('is_active', false)
            ->whereRaw("LOWER(descricao) IN ('cancelado', 'perdido')")
            ->update(['tipo' => 'perdido']);

        // O que sobrou de is_active = false fica com o default 'aberto' — ver
        // o bloco sobre "Pausado" no topo.

        // --- 3. Cada lead herda o funil do seu estágio ---
        DB::statement('UPDATE usuarios u JOIN estagios e ON e.id = u.estagio_id SET u.funil_id = e.funil_id');

        // Lead sem estágio ainda pertence a um funil: o padrão do tenant.
        DB::statement('UPDATE usuarios u JOIN funis f ON f.tenant_id = u.tenant_id AND f.is_default = 1 SET u.funil_id = f.id WHERE u.funil_id IS NULL');

        // --- 4. Fecha as portas ---
        // Todo estágio tem funil a partir daqui; e `is_active` sai para não
        // restar duas fontes de verdade discordando sobre o mesmo estágio.
        DB::statement('ALTER TABLE `estagios` MODIFY `funil_id` BIGINT UNSIGNED NOT NULL');

        Schema::table('estagios', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }

    public function down(): void
    {
        Schema::table('estagios', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('ordem');
        });

        // 'aberto' era is_active = true; ganho e perdido eram false. Estágios
        // que vieram de "Pausado" voltam como ativos, não como arquivados: a
        // informação que os distinguia não existe mais.
        DB::table('estagios')->update(['is_active' => true]);
        DB::table('estagios')->whereIn('tipo', ['ganho', 'perdido'])->update(['is_active' => false]);

        DB::statement('ALTER TABLE `estagios` MODIFY `funil_id` BIGINT UNSIGNED NULL');
        DB::table('usuarios')->update(['funil_id' => null]);
        DB::table('estagios')->update(['funil_id' => null]);
        DB::table('funis')->delete();
    }
};
