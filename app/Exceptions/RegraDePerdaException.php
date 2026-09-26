<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Violação de uma invariante do catálogo de motivos — hoje, arquivar o último
 * motivo disponível.
 *
 * Irmã de RegraDeFunilException: mesmo formato de resposta, domínio diferente.
 * São duas classes e não uma genérica para que o `catch` possa distinguir de
 * qual configuração veio a recusa.
 */
class RegraDePerdaException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => $this->getMessage(),
            'erros' => ['motivo_perda' => [$this->getMessage()]],
        ], 422);
    }
}
