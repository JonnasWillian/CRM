<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ImportarLeads;
use App\Models\Importacao;
use App\Services\Importacao\CamposDeImportacao;
use App\Services\Importacao\PreviaDeImportacao;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Importar leads (leads.view-all): enviar, mapear e iniciar, acompanhar, baixar erros. */
class ImportacaoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Importacao::query()->latest()->limit(10)->get()->map(fn ($i) => self::serializar($i))]);
    }

    public function store(Request $request, PreviaDeImportacao $previa): JsonResponse
    {
        $request->validate(
            ['arquivo' => ['required', 'file', 'max:10240', 'mimes:csv,txt']],
            ['arquivo.mimes' => 'Envie um arquivo .csv (no Excel: Salvar como › CSV).', 'arquivo.max' => 'O arquivo pode ter no máximo 10 MB.'],
        );

        return response()->json(['data' => self::serializar($previa($request->file('arquivo'), $request->user()))], 201);
    }

    public function show(Importacao $importacao): JsonResponse
    {
        return response()->json(['data' => self::serializar($importacao)]);
    }

    public function iniciar(Request $request, Importacao $importacao): JsonResponse
    {
        abort_unless($importacao->status === Importacao::AGUARDANDO_MAPEAMENTO, 409, 'Esta importação já foi iniciada.');

        $tenant = app(CurrentTenant::class)->id();
        $colunas = count($importacao->cabecalhos ?? []) - 1;
        $dados = $request->validate([
            'mapeamento' => ['required', 'array'],
            'mapeamento.nome' => ['required', 'integer', "between:0,{$colunas}"],
            'mapeamento.email' => ['required', 'integer', "between:0,{$colunas}"],
            'mapeamento.*' => ['nullable', 'integer', "between:0,{$colunas}"],
            'opcoes.duplicados' => ['required', Rule::in(['ignorar', 'atualizar'])],
            'opcoes.funil_id' => ['nullable', 'integer', Rule::exists('funis', 'id')->where('tenant_id', $tenant)->whereNull('deleted_at')],
            'opcoes.estagio_id' => ['nullable', 'integer', Rule::exists('estagios', 'id')->where('tenant_id', $tenant)->whereNull('deleted_at')],
            'opcoes.user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenant)->where('ativo', true)],
        ], [
            'mapeamento.nome.required' => 'Escolha a coluna do nome.',
            'mapeamento.email.required' => 'Escolha a coluna do e-mail.',
        ]);

        $mapa = array_intersect_key(array_filter($dados['mapeamento'], fn ($v) => $v !== null), CamposDeImportacao::CAMPOS);
        $importacao->forceFill(['mapeamento' => array_map('intval', $mapa), 'opcoes' => $dados['opcoes'] ?? [], 'status' => Importacao::NA_FILA])->save();
        ImportarLeads::dispatch($importacao->id);

        return response()->json(['message' => 'Importação iniciada.', 'data' => self::serializar($importacao)]);
    }

    /** As linhas com erro ou aviso, no formato original + a mensagem, para corrigir e reimportar. */
    public function erros(Importacao $importacao): StreamedResponse
    {
        $nome = 'erros-'.pathinfo($importacao->nome_original, PATHINFO_FILENAME).'.csv';

        return response()->streamDownload(function () use ($importacao) {
            $saida = fopen('php://output', 'w');
            fwrite($saida, "\xEF\xBB\xBF");
            fputcsv($saida, ['linha', 'tipo', 'erro', ...($importacao->cabecalhos ?? [])], ';', '"', '');
            foreach ($importacao->erros ?? [] as $e) {
                fputcsv($saida, [$e['linha'], $e['aviso'] ? 'aviso' : 'erro', $e['erro'], ...($e['original'] ?? [])], ';', '"', '');
            }
            fclose($saida);
        }, $nome, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function serializar(Importacao $i): array
    {
        $erros = collect($i->erros ?? []);

        return [
            'id' => $i->id,
            'nome_original' => $i->nome_original,
            'delimitador' => $i->delimitador,
            'codificacao' => $i->codificacao,
            'cabecalhos' => $i->cabecalhos,
            'amostra' => $i->amostra,
            'mapeamento' => $i->mapeamento,
            'opcoes' => $i->opcoes,
            'status' => $i->status,
            'total_linhas' => $i->total_linhas,
            'processadas' => $i->processadas,
            'criadas' => $i->criadas,
            'atualizadas' => $i->atualizadas,
            'ignoradas' => $i->ignoradas,
            'com_erro' => $i->com_erro,
            'avisos' => $erros->where('aviso', true)->count(),
            'erros' => $erros->take(50)->map(fn ($e) => ['linha' => $e['linha'], 'erro' => $e['erro'], 'aviso' => $e['aviso']])->values(),
            'mensagem_falha' => $i->mensagem_falha,
            'created_at' => $i->created_at?->toIso8601String(),
            'concluida_em' => $i->concluida_em?->toIso8601String(),
        ];
    }
}
