<?php

namespace App\Jobs;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\Importacao;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use App\Rules\TelefoneE164;
use App\Services\Importacao\PreviaDeImportacao;
use App\Services\Leads\CadastrarLead;
use App\Support\Csv\CelulaSegura;
use App\Support\LimitesDeTexto;
use App\Support\Telefone;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Importa as linhas de uma Importacao já mapeada. Roda fora do HTTP: define
 * tenant, team do spatie e o usuário (causer do histórico) a partir da
 * própria importação, nunca do ambiente.
 *
 * Por linha: erro barra a linha (sem nome/e-mail válidos, e-mail repetido
 * no arquivo, lead na lixeira, regra de perda); aviso não barra (telefone,
 * estágio ou responsável não reconhecidos viram o padrão). Duplicado por
 * e-mail: `ignorar` ou `atualizar` (atualizar não apaga campo vazio).
 */
class ImportarLeads implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    private Importacao $imp;

    /** @var list<array{linha:int, erro:string, aviso:bool, original:list<string>}> */
    private array $erros = [];

    public function __construct(public readonly int $importacaoId) {}

    public function handle(): void
    {
        $this->imp = Importacao::withoutGlobalScopes()->findOrFail($this->importacaoId);
        $autor = User::findOrFail($this->imp->user_id);
        app(CurrentTenant::class)->set(Tenant::findOrFail($this->imp->tenant_id));
        setPermissionsTeamId($this->imp->tenant_id);
        Auth::setUser($autor);

        $this->imp->forceFill(['status' => Importacao::PROCESSANDO, 'iniciada_em' => now(), 'processadas' => 0, 'criadas' => 0, 'atualizadas' => 0, 'ignoradas' => 0, 'com_erro' => 0])->save();

        try {
            $this->processar($autor);
            $this->imp->forceFill(['status' => Importacao::CONCLUIDA, 'concluida_em' => now(), 'erros' => $this->erros])->save();
        } catch (Throwable $e) {
            report($e);
            $this->imp->forceFill(['status' => Importacao::FALHOU, 'mensagem_falha' => 'A importação parou por um erro inesperado. As linhas já processadas foram mantidas.', 'erros' => $this->erros])->save();
        }
    }

    /** Timeout ou queda do worker: o catch de handle() não roda; sem isto a importação ficaria "processando" para sempre. */
    public function failed(Throwable $e): void
    {
        Importacao::withoutGlobalScopes()->whereKey($this->importacaoId)->update([
            'status' => Importacao::FALHOU,
            'mensagem_falha' => 'A importação foi interrompida (tempo esgotado ou falha do servidor). As linhas já processadas foram mantidas; importe de novo o restante.',
        ]);
    }

    private function processar(User $autor): void
    {
        $linhas = PreviaDeImportacao::ler((string) Storage::disk('local')->get($this->imp->arquivo), $this->imp->delimitador);
        array_shift($linhas);

        $mapa = $this->imp->mapeamento ?? [];
        $opcoes = $this->imp->opcoes ?? [];
        $funil = ! empty($opcoes['funil_id']) ? Funil::find($opcoes['funil_id']) : Funil::padrao();
        $estagios = Estagio::query()->get(['id', 'funil_id', 'descricao', 'tipo', 'ordem']);
        $estagioPadrao = ! empty($opcoes['estagio_id']) ? $estagios->firstWhere('id', (int) $opcoes['estagio_id'])
            : $estagios->where('funil_id', $funil?->id)->where('tipo', Estagio::TIPO_ABERTO)->sortBy('ordem')->first();
        $membros = User::doTenant($this->imp->tenant_id)->ativos()->get(['id', 'name', 'email']);
        $responsavelPadrao = ! empty($opcoes['user_id']) ? (int) $opcoes['user_id'] : $autor->id;
        $atualizar = ($opcoes['duplicados'] ?? 'ignorar') === 'atualizar';
        $cadastrar = app(CadastrarLead::class);
        $vistos = [];

        foreach ($linhas as $i => $linha) {
            $n = $i + 2; // linha 1 = cabeçalho
            $valor = fn (string $campo) => isset($mapa[$campo]) ? CelulaSegura::desarmar(trim((string) ($linha[$mapa[$campo]] ?? ''))) : '';

            $email = Str::lower($valor('email'));
            if ($email !== '' && isset($vistos[$email])) {
                $this->erro($n, "E-mail repetido no arquivo (já aparece na linha {$vistos[$email]}).", $linha);

                continue;
            }
            if ($email !== '') {
                $vistos[$email] = $n;
            }

            $dados = [
                'nome' => $valor('nome'),
                'email' => $email,
                'descricao' => $valor('descricao') ?: null,
                'valor_estimado' => self::numero($valor('valor_estimado')),
                'probabilidade' => $valor('probabilidade') === '' ? null : (int) preg_replace('/\D/', '', $valor('probabilidade')),
                'previsao_fechamento' => self::data($valor('previsao_fechamento')),
            ];

            $v = Validator::make($dados, [
                'nome' => ['required', 'string', 'min:2', 'max:'.LimitesDeTexto::NOME],
                'email' => ['required', 'email', 'max:'.LimitesDeTexto::EMAIL],
                'descricao' => ['nullable', 'string', 'max:'.LimitesDeTexto::DESCRICAO],
                'valor_estimado' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
                'probabilidade' => ['nullable', 'integer', 'between:0,100'],
            ], [
                'nome.required' => 'Nome é obrigatório.', 'email.required' => 'E-mail é obrigatório.', 'email.email' => 'E-mail inválido.',
                'probabilidade.between' => 'Probabilidade deve ir de 0 a 100.', 'valor_estimado.numeric' => 'Valor estimado inválido.',
            ]);
            if ($v->fails()) {
                $this->erro($n, $v->errors()->first(), $linha);

                continue;
            }

            $telefone = Telefone::normalizar($valor('telefone'));
            if ($telefone !== null && Validator::make(['t' => $telefone], ['t' => [new TelefoneE164]])->fails()) {
                $this->aviso($n, "Telefone “{$valor('telefone')}” não reconhecido; a linha entrou sem telefone.", $linha);
                $telefone = null;
            }

            $existente = Usuario::withTrashed()->where('email', $email)->first();
            if ($existente?->trashed()) {
                $this->erro($n, 'Já existe um lead com este e-mail na Lixeira. Restaure-o antes de importar.', $linha);

                continue;
            }
            if ($existente) {
                if (! $atualizar) {
                    $this->imp->ignoradas++;
                } else {
                    $existente->fill(array_filter([...$dados, 'telefone' => $telefone, 'email' => null], fn ($x) => $x !== null && $x !== ''))->save();
                    $this->imp->atualizadas++;
                }
                $this->avancar();

                continue;
            }

            $estagio = $estagioPadrao;
            if ($valor('estagio') !== '') {
                $achado = $estagios->first(fn ($e) => $e->funil_id === $funil?->id && Str::lower($e->descricao) === Str::lower($valor('estagio')));
                $achado ? $estagio = $achado : $this->aviso($n, "Estágio “{$valor('estagio')}” não existe no funil; a linha entrou em “{$estagioPadrao?->descricao}”.", $linha);
            }

            $responsavel = $responsavelPadrao;
            if ($valor('responsavel') !== '') {
                $achado = $membros->first(fn ($m) => Str::lower($m->email) === Str::lower($valor('responsavel')) || Str::lower($m->name) === Str::lower($valor('responsavel')));
                $achado ? $responsavel = $achado->id : $this->aviso($n, "Responsável “{$valor('responsavel')}” não é um membro ativo; a linha ficou com o padrão.", $linha);
            }

            try {
                $cadastrar([...$dados, 'telefone' => $telefone, 'funil_id' => $funil?->id, 'estagio_id' => $estagio?->id, 'user_id' => $responsavel], ['motivo_perda_id' => null, 'observacao' => null], $autor);
                $this->imp->criadas++;
                $this->avancar();
            } catch (ValidationException $e) {
                $this->erro($n, $e->validator->errors()->has('motivo_perda_id') ? 'O estágio escolhido é de perda e exige motivo; importe em outro estágio.' : $e->getMessage(), $linha);
            } catch (\App\Exceptions\LimiteDoPlanoException $e) {
                // O plano encheu no meio: para aqui, com o que já entrou.
                $this->erro($n, $e->getMessage(), $linha);
                $this->imp->mensagem_falha = $e->getMessage();
                break;
            }
        }

        $this->imp->processadas = count($linhas);
    }

    private function avancar(): void
    {
        $this->imp->processadas++;
        if ($this->imp->processadas % 100 === 0) {
            $this->imp->forceFill(['erros' => $this->erros])->save();
        }
    }

    private function erro(int $linha, string $mensagem, array $original): void
    {
        $this->imp->com_erro++;
        $this->guardar($linha, $mensagem, false, $original);
        $this->avancar();
    }

    private function aviso(int $linha, string $mensagem, array $original): void
    {
        $this->guardar($linha, $mensagem, true, $original);
    }

    private function guardar(int $linha, string $mensagem, bool $aviso, array $original): void
    {
        if (count($this->erros) < (int) config('leads.importacao.max_erros_guardados', 200)) {
            $this->erros[] = ['linha' => $linha, 'erro' => $mensagem, 'aviso' => $aviso, 'original' => $original];
        }
    }

    /** "1.500,00", "1500,5", "1500.5", "R$ 1.500" → float. */
    public static function numero(string $v): ?float
    {
        $limpo = preg_replace('/[^\d,.-]/', '', $v);
        if ($limpo === '' || $limpo === '-') {
            return null;
        }
        $normalizado = str_contains($limpo, ',') ? str_replace(['.', ','], ['', '.'], $limpo) : $limpo;

        return is_numeric($normalizado) ? round((float) $normalizado, 2) : null;
    }

    /** dd/mm/aaaa ou aaaa-mm-dd. */
    public static function data(string $v): ?string
    {
        if ($v === '') {
            return null;
        }
        foreach (['d/m/Y', 'Y-m-d', 'd/m/y'] as $formato) {
            try {
                $d = Carbon::createFromFormat($formato, $v);
                if ($d !== false && $d->format($formato) === $v) {
                    return $d->toDateString();
                }
            } catch (Throwable) {
            }
        }

        return null;
    }
}
