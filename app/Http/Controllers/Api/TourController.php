<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Estado do passo a passo do próprio usuário. O front (useTour) decide os
 * passos; aqui só se guarda onde a pessoa está, para retomar depois de
 * recarregar ou trocar de computador.
 */
class TourController extends Controller
{
    private const ESTADOS = [
        'iniciar' => 'em_andamento',
        'avancar' => 'em_andamento',
        'refazer' => 'em_andamento',
        'concluir' => 'concluido',
        'cancelar' => 'cancelado',
    ];

    public function update(Request $request): JsonResponse
    {
        $dados = $request->validate([
            'acao' => ['required', 'in:'.implode(',', array_keys(self::ESTADOS))],
            'passo' => ['required_if:acao,iniciar,avancar,refazer', 'nullable', 'string', 'regex:/^[a-z0-9-]{1,40}$/'],
        ], ['passo.regex' => 'Passo inválido.', 'passo.required_if' => 'Informe o passo.']);

        $user = $request->user();
        $user->forceFill([
            'tour_estado' => self::ESTADOS[$dados['acao']],
            'tour_passo' => self::ESTADOS[$dados['acao']] === 'em_andamento' ? $dados['passo'] : null,
            // Refazer mostra a versão curta: só aponta os lugares, sem criar lead de teste.
            ...($dados['acao'] === 'refazer' ? ['tour_versao' => 'curta'] : []),
        ])->save();

        return response()->json($user->fresh()->only(['tour_estado', 'tour_versao', 'tour_passo']));
    }
}
