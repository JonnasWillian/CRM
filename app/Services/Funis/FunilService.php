<?php

namespace App\Services\Funis;

use App\Exceptions\RegraDeFunilException;
use App\Models\Estagio;
use App\Models\Funil;
use Illuminate\Support\Facades\DB;

/**
 * Operações de funil que envolvem mais de uma linha ou mais de uma tabela.
 *
 * Elas não moram no controller porque nenhuma delas é uma escrita só: criar um
 * funil cria também seu primeiro estágio, marcar um padrão desmarca o anterior,
 * arquivar um funil arquiva seus estágios. Cada uma precisa ser tudo ou nada.
 *
 * Todas as consultas passam pelo TenantScope, então "o tenant ativo" está
 * implícito e não aparece como argumento.
 */
class FunilService
{
    public function criar(array $dados): Funil
    {
        return DB::transaction(function () use ($dados) {
            $funil = new Funil();
            $funil->fill($dados);
            $funil->ordem = $dados['ordem'] ?? ((int) Funil::max('ordem') + 1);
            $funil->save();

            // Um funil sem estágio aberto não recebe lead nenhum. Em vez de
            // entregar ao tenant um funil inutilizável até ele descobrir por
            // quê, o primeiro estágio nasce junto — renomeável como qualquer
            // outro.
            $estagio = new Estagio();
            $estagio->funil_id = $funil->id;
            $estagio->descricao = 'Novo estágio';
            $estagio->ordem = 1;
            $estagio->tipo = Estagio::TIPO_ABERTO;
            $estagio->save();

            // Primeiro funil do tenant vira o padrão sozinho: sem padrão, um
            // lead criado sem funil explícito não teria onde cair.
            if (Funil::where('is_default', true)->doesntExist()) {
                $this->definirPadrao($funil);
            }

            return $funil->refresh();
        });
    }

    public function atualizar(Funil $funil, array $dados): Funil
    {
        $funil->fill($dados);
        $funil->save();

        return $funil;
    }

    public function definirPadrao(Funil $funil): Funil
    {
        return DB::transaction(function () use ($funil) {
            Funil::where('is_default', true)->update(['is_default' => false]);

            $funil->is_default = true;
            $funil->save();

            return $funil;
        });
    }

    /**
     * Arquivar é soft delete. Duas recusas, e o motivo de cada uma:
     *
     * - O funil padrão não sai, porque é para ele que vai um lead criado sem
     *   funil explícito. Sem padrão, o cadastro de lead quebraria.
     * - Funil com lead dentro não sai, porque o lead ficaria apontando para um
     *   funil que nenhuma tela mostra — invisível no Kanban e vivo no banco.
     *   O caminho é mover os leads primeiro, que é uma ação que existe na UI.
     *
     * Isso difere de propósito do comportamento de estágio arquivado, que
     * continua aparecendo enquanto segura lead: uma coluna a mais no quadro é
     * barata, um quadro inteiro fantasma não é.
     */
    public function arquivar(Funil $funil): void
    {
        if ($funil->is_default) {
            throw new RegraDeFunilException('O funil padrão não pode ser arquivado. Defina outro funil como padrão antes.');
        }

        if ($funil->leads()->exists()) {
            throw new RegraDeFunilException('Este funil ainda tem leads. Mova os leads para outro funil antes de arquivar.');
        }

        DB::transaction(function () use ($funil) {
            $funil->estagios()->delete();
            $funil->delete();
        });
    }

    /**
     * @param  array<int, int>  $idsNaOrdem
     */
    public function reordenar(array $idsNaOrdem): void
    {
        DB::transaction(function () use ($idsNaOrdem) {
            foreach ($idsNaOrdem as $posicao => $id) {
                // O where passa pelo TenantScope, então um id de outro tenant
                // simplesmente não casa — nenhuma linha é tocada.
                Funil::whereKey($id)->update(['ordem' => $posicao + 1]);
            }
        });
    }
}
