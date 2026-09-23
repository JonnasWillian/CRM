<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Violação de uma invariante de funil — arquivar o funil padrão, remover o
 * último estágio aberto, mover um lead para um estágio de outro funil.
 *
 * Tem `render()` própria para devolver 422 com a mensagem no mesmo formato que
 * o frontend já consome nos erros de validação (`erros`), em vez de exigir um
 * handler em bootstrap/app.php. 422 e não 400: o pedido está bem formado, o que
 * ele descreve é que não é um estado válido.
 */
class RegraDeFunilException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => $this->getMessage(),
            'erros' => ['funil' => [$this->getMessage()]],
        ], 422);
    }
}
