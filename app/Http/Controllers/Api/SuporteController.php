<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChamadoDeSuporteRequest;
use App\Models\ChamadoDeSuporte;
use App\Services\Suporte\EntregaDeChamado;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Assistente de ajuda e feedback. Toda resposta fica gravada; o e-mail ao
 * suporte (config suporte.email) só sai quando a pessoa pede. Quem escreve
 * é sempre o usuário autenticado: nome, e-mail da conta e empresa vêm dele.
 */
class SuporteController extends Controller
{
    public function store(ChamadoDeSuporteRequest $request): JsonResponse
    {
        $user = $request->user();
        $destino = (string) config('suporte.email');

        $hoje = ChamadoDeSuporte::where('user_id', $user->id)->where('created_at', '>=', Carbon::now()->subDay())->count();
        if ($hoje >= (int) config('suporte.maximo_por_dia')) {
            return response()->json(['message' => "Você já enviou várias mensagens hoje. Se for urgente, escreva para {$destino}."], 429);
        }

        $dados = $request->validated();
        $chamado = new ChamadoDeSuporte(collect($dados)->only(['tipo', 'area', 'impacto', 'mensagem', 'nota', 'pagina'])->all());
        $chamado->user_id = $user->id;
        $chamado->tenant_id = $user->tenant_id;
        $chamado->email_contato = $dados['email_contato'] ?? $user->email;
        $chamado->acionou_suporte = (bool) ($dados['acionar_suporte'] ?? false);
        $chamado->navegador = Str::limit((string) $request->userAgent(), 250, '');
        $chamado->save();

        if ($chamado->acionou_suporte) {
            // Depois da resposta: a pessoa não espera o serviço de e-mail, e falha na entrega não derruba o envio.
            dispatch(fn () => app(EntregaDeChamado::class)->entregar($chamado))->afterResponse();
        }

        return response()->json([
            'protocolo' => $chamado->id,
            'suporte_acionado' => $chamado->acionou_suporte,
            'email_contato' => $chamado->email_contato,
        ], 201);
    }
}
