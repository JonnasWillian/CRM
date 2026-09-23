<?php

namespace App\Services\Funis;

use App\Exceptions\RegraDeFunilException;
use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Usuario;

/**
 * Move um lead de um funil para outro, no estágio de destino escolhido.
 *
 * O lead vive em um funil por vez, então mover é uma escrita só: funil e
 * estágio mudam juntos. O histórico (EstagioHistorico) e o evento
 * `funil_alterado` no activity log saem do UsuarioObserver, que observa
 * exatamente essas duas colunas — nada é registrado aqui.
 *
 * A checagem de que o estágio pertence ao funil de destino é o que impede o
 * estado que nenhuma tela sabe desenhar: um lead cujo funil diz "Pós-venda" e
 * cujo estágio é uma coluna de "Vendas". Ela é redundante com a validação do
 * FormRequest de propósito — o serviço é chamável de fora de uma request (um
 * job, um comando) e a invariante não pode depender de quem chamou.
 */
class MoverLeadDeFunil
{
    public function __invoke(Usuario $lead, Funil $destino, Estagio $estagio): Usuario
    {
        if ((int) $estagio->funil_id !== (int) $destino->id) {
            throw new RegraDeFunilException('O estágio escolhido não pertence ao funil de destino.');
        }

        if ($estagio->trashed()) {
            throw new RegraDeFunilException('Não é possível mover um lead para um estágio arquivado.');
        }

        $lead->update([
            'funil_id' => $destino->id,
            'estagio_id' => $estagio->id,
        ]);

        return $lead;
    }
}
