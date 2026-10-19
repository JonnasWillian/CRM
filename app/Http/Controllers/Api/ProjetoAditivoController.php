<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Projeto;
use App\Models\ProjetoAditivo;
use App\Support\ActivityLog\LeadActivity;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Renovações e aditivos de contrato recorrente. Mexer exige poder editar o projeto. */
class ProjetoAditivoController extends Controller
{
    public function store(Request $request, Projeto $projeto): JsonResponse
    {
        $this->authorize('update', $projeto);
        if (! $projeto->ehRecorrente()) {
            throw self::recusar('Só projetos recorrentes têm renovação e aditivos.');
        }

        $dados = $request->validate([
            'tipo' => ['required', Rule::in(ProjetoAditivo::TIPOS)],
            'meses' => ['required', 'integer', 'between:1,240'],
            'valor_mensal' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ], [
            'meses.required' => 'Informe quantos meses o contrato ganha.',
            'meses.between' => 'De 1 a 240 meses.',
        ]);

        $aditivo = DB::transaction(function () use ($projeto, $dados, $request) {
            $aditivo = new ProjetoAditivo([...$dados, 'projeto_id' => $projeto->id, 'user_id' => $request->user()->id]);
            $aditivo->tenant_id = $projeto->tenant_id;
            $aditivo->save();
            $projeto->save(); // recalcula total e fim (ProjetoObserver)

            return $aditivo;
        });

        $projeto = $projeto->fresh(['status', 'aditivos']);
        $fim = $projeto->recorrencia['fim'] ?? null;
        $rotulo = $aditivo->tipo === 'renovacao' ? 'Contrato renovado' : 'Aditivo no contrato';
        $resumo = "+{$aditivo->meses} ".($aditivo->meses === 1 ? 'mês' : 'meses')
            .($aditivo->valor_mensal !== null ? ' · R$ '.number_format((float) $aditivo->valor_mensal, 2, ',', '.').'/mês' : '')
            .($fim ? ' · até '.\Illuminate\Support\Carbon::parse($fim)->format('d/m/Y') : '');

        LeadActivity::registrar($projeto, $projeto->usuario_id, 'projeto_aditivo', $rotulo, [
            'projeto_nome' => $projeto->nome,
            'tipo_aditivo' => $aditivo->tipo,
            'resumo' => $resumo,
        ]);

        return response()->json(['message' => "{$rotulo}: {$resumo}.", 'data' => $projeto], 201);
    }

    public function destroy(ProjetoAditivo $projetoAditivo): JsonResponse
    {
        $projeto = $projetoAditivo->projeto;
        $this->authorize('update', $projeto);

        $ultimo = $projeto->aditivos()->reorder()->orderByDesc('id')->value('id');
        if ($ultimo !== $projetoAditivo->id) {
            throw self::recusar('Só o último aditivo pode ser removido; remova os mais recentes antes.');
        }

        DB::transaction(function () use ($projetoAditivo, $projeto) {
            $projetoAditivo->delete();
            $projeto->save();
        });

        return response()->json(['message' => 'Aditivo removido.', 'data' => $projeto->fresh(['status', 'aditivos'])]);
    }

    private static function recusar(string $mensagem): HttpResponseException
    {
        return new HttpResponseException(response()->json(['message' => $mensagem], 422));
    }
}
