<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\EventoDeGateway;
use App\Services\Pagamentos\Asaas\CobrancasDoAsaas;
use App\Services\Pagamentos\Asaas\FalhaNoAsaas;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Avisos do Asaas (cadastrados com `php artisan asaas:webhook`). Sem sessão:
 * a autenticação é o token do header `asaas-access-token`.
 *
 * Responde 200 a tudo que foi tratado, inclusive o que foi recusado ou
 * ignorado: 15 falhas seguidas pausam a fila de avisos no Asaas. Só quando
 * a API não responde ao conferir a cobrança é que pede reenvio (503).
 */
class AsaasController extends Controller
{
    public function __invoke(Request $request, CobrancasDoAsaas $cobrancas): JsonResponse
    {
        $esperado = (string) config('services.asaas.token_webhook');
        abort_if($esperado === '', 404);
        if (! hash_equals($esperado, (string) $request->header('asaas-access-token'))) {
            return response()->json(['message' => 'Token inválido.'], 401);
        }

        $id = $request->input('id');
        $tipo = $request->input('event');
        if (! is_string($id) || ! is_string($tipo) || $id === '' || $tipo === '') {
            return response()->json(['message' => 'Aviso sem id ou evento.'], 400);
        }

        $evento = EventoDeGateway::firstOrCreate(
            ['gateway' => 'asaas', 'evento_id' => Str::limit($id, 100, '')],
            ['tipo' => Str::limit($tipo, 60, ''), 'cobranca_id' => Str::limit((string) $request->input('payment.id'), 40, '') ?: null, 'payload' => $request->all()],
        );
        if ($evento->processado_em !== null) {
            return response()->json(['resultado' => 'duplicado']);
        }

        try {
            $resultado = $cobrancas->processarEvento($request->all());
        } catch (FalhaNoAsaas $e) {
            if ($e->temporaria) {
                return response()->json(['message' => 'Não deu para conferir a cobrança agora. Reenvie.'], 503);
            }
            Log::error("Asaas: aviso {$id} ({$tipo}) não processado.", ['erro' => $e->getMessage()]);
            $resultado = 'erro: '.$e->getMessage();
        } catch (DomainException $e) {
            // Regra (fatura cancelada entre a leitura e a trava): reenviar não muda nada.
            Log::warning("Asaas: aviso {$id} ({$tipo}) recusado pela regra da fatura.", ['erro' => $e->getMessage()]);
            $resultado = 'erro: '.$e->getMessage();
        }

        $evento->forceFill(['processado_em' => Carbon::now(), 'resultado' => Str::limit($resultado, 250)])->save();

        return response()->json(['resultado' => $resultado]);
    }
}
