<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Request;

/**
 * O plano não comporta mais um usuário, funil ou lead. Na API: 422 com
 * `limite` (usuarios|funis|leads) para o front oferecer o upgrade; no site
 * (formulário Inertia): volta com a mensagem em flash.
 */
class LimiteDoPlanoException extends Exception
{
    public function __construct(public readonly string $limite, string $mensagem)
    {
        parent::__construct($mensagem);
    }

    public function render(Request $request)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => $this->getMessage(), 'limite' => $this->limite], 422);
        }

        return back()->with('erro', $this->getMessage());
    }
}
