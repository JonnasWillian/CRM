<?php

namespace App\Services\Funis;

use App\Exceptions\RegraDeFunilException;
use App\Models\Estagio;
use App\Models\Funil;
use Illuminate\Support\Facades\DB;

/**
 * Operações de estágio dentro de um funil.
 *
 * A invariante que este serviço existe para proteger: **todo funil precisa de
 * ao menos um estágio do tipo `aberto`**. É nele que entra um lead novo e é
 * para ele que um lead é movido quando muda de funil. Um funil sem estágio
 * aberto não é um funil vazio — é um funil onde o cadastro de lead falha, e
 * falha longe daqui, no momento em que alguém tenta usar.
 *
 * Por isso a checagem aparece em dois lugares: ao arquivar um estágio e ao
 * mudar seu tipo. São os dois caminhos que podem zerar a contagem.
 */
class EstagioService
{
    public function criar(Funil $funil, array $dados): Estagio
    {
        $estagio = new Estagio();
        $estagio->fill($dados);
        $estagio->funil_id = $funil->id;
        // withTrashed: um estágio arquivado ainda ocupa sua posição no funil
        // enquanto segura leads, e reaproveitar a `ordem` dele faria as duas
        // colunas empatarem no quadro.
        $estagio->ordem = $dados['ordem'] ?? ((int) Estagio::withTrashed()->where('funil_id', $funil->id)->max('ordem') + 1);
        $estagio->save();

        return $estagio;
    }

    public function atualizar(Estagio $estagio, array $dados): Estagio
    {
        $tipoNovo = $dados['tipo'] ?? $estagio->tipo;

        if ($estagio->tipo === Estagio::TIPO_ABERTO && $tipoNovo !== Estagio::TIPO_ABERTO) {
            $this->garantirQueSobraEstagioAberto($estagio);
        }

        $estagio->fill($dados);
        $estagio->save();

        return $estagio;
    }

    public function arquivar(Estagio $estagio): void
    {
        if ($estagio->tipo === Estagio::TIPO_ABERTO) {
            $this->garantirQueSobraEstagioAberto($estagio);
        }

        // Soft delete. O estágio continua vindo no Kanban enquanto segurar
        // lead, marcado como somente-saída — o comportamento que
        // KanbanArchivedEstagioTest fixa desde antes dos funis.
        $estagio->delete();
    }

    /**
     * Desarquivar. Não há invariante a checar: devolver um estágio ao funil só
     * aumenta o conjunto, nunca deixa o funil sem estágio aberto.
     */
    public function restaurar(Estagio $estagio): void
    {
        $estagio->restore();
    }

    /**
     * @param  array<int, int>  $idsNaOrdem
     */
    public function reordenar(Funil $funil, array $idsNaOrdem): void
    {
        DB::transaction(function () use ($funil, $idsNaOrdem) {
            foreach ($idsNaOrdem as $posicao => $id) {
                // O filtro por funil_id impede reordenar, por id, um estágio de
                // outro funil do mesmo tenant — o TenantScope não pegaria isso,
                // já que os dois funis pertencem ao mesmo tenant.
                Estagio::withTrashed()
                    ->whereKey($id)
                    ->where('funil_id', $funil->id)
                    ->update(['ordem' => $posicao + 1]);
            }
        });
    }

    private function garantirQueSobraEstagioAberto(Estagio $estagio): void
    {
        $sobram = Estagio::where('funil_id', $estagio->funil_id)
            ->whereKeyNot($estagio->getKey())
            ->aberto()
            ->exists();

        if (! $sobram) {
            throw new RegraDeFunilException('Este é o último estágio aberto do funil. Crie outro estágio aberto antes de arquivar ou mudar o tipo deste.');
        }
    }
}
