<?php

namespace App\Support\Perdas;

use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * O que pode ser perdido: hoje o lead (Usuario) e o projeto (Projeto).
 *
 * Os dois respondem à mesma pergunta — "isto está perdido?" — por caminhos
 * diferentes: o lead pelo `tipo` do seu estágio, o projeto pelo `is_lost` do
 * seu status. Sem este contrato, o serviço que exige o motivo teria de
 * conhecer as duas formas, e um terceiro perdível no futuro obrigaria a mexer
 * nele em vez de só implementar a interface.
 *
 * `estadoSeriaPerda()` recebe os atributos AINDA NÃO aplicados. É o que permite
 * exigir o motivo antes de gravar, em vez de descobrir a perda depois do save —
 * quando já não dá para recusar.
 */
interface Perdivel
{
    /** A entidade já está, neste momento, num estado de perda. */
    public function estadoAtualEhPerda(): bool;

    /**
     * A entidade FICARIA perdida se estes atributos fossem aplicados.
     *
     * @param  array<string, mixed>  $atributos  payload de update ainda não salvo
     */
    public function estadoSeriaPerda(array $atributos): bool;

    /** Quanto valia o que se perdeu, para a foto em `perdas.valor`. */
    public function valorDaPerda(): ?float;

    public function perdas(): MorphMany;
}
