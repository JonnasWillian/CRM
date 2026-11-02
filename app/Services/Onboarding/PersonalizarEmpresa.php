<?php

namespace App\Services\Onboarding;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\MotivoPerda;
use App\Models\PerfilDeEmpresa;
use App\Models\TarefaPadrao;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Usuario;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Aplica as respostas do questionário de primeiro acesso (config/onboarding.php):
 * estágios do ramo no funil padrão, motivos de perda do ramo, modelos de
 * tarefa por canal para quem respondeu, visão do Dashboard e versão do
 * passo a passo. Uma vez só: perfil resolvido não é reaplicado.
 *
 * Só age na empresa que tem a linha de perfil criada no cadastro: empresa
 * anterior ao questionário não é personalizada (nem ganha a linha aqui).
 *
 * Nunca apaga o que a empresa usou: os estágios só são trocados se o funil
 * padrão não tem nenhum lead (nem na lixeira); motivos e modelos só entram.
 */
class PersonalizarEmpresa
{
    /** Mesma sequência de cores abertas do TenantBootstrapper. */
    private const CORES_ABERTAS = ['#2b6cb0', '#b83280', '#9c6612', '#6b46c1'];

    private const COR_GANHO = '#1a7544';

    private const COR_PERDIDO = '#b42318';

    private const MAX_MODELOS = 4;

    public function aplicar(Tenant $tenant, User $quem, array $respostas): ?PerfilDeEmpresa
    {
        return DB::transaction(function () use ($tenant, $quem, $respostas) {
            $perfil = $this->perfilTravado($tenant);
            if ($perfil === null || $perfil->resolvido()) {
                return $perfil;
            }

            $this->montarFunil($tenant, $respostas['ramo'] ?? 'outro');
            $this->acrescentarMotivos($tenant, $respostas['ramo'] ?? 'outro');
            $this->criarModelos($tenant, $quem, $respostas['canais'] ?? []);

            $quem->forceFill([
                'dashboard_visao' => ($respostas['cargo'] ?? null) === 'vendedor' ? 'meus' : 'equipe',
                'tour_versao' => PreferenciasDaEmpresa::versaoDoTour($respostas),
            ])->save();

            $dicas = (bool) ($respostas['dicas_whatsapp'] ?? false);
            $perfil->forceFill([
                'respostas' => Arr::only($respostas, ['experiencia', 'cargo', 'ramo', 'tamanho', 'para_quem', 'canais', 'origens', 'conheceu']),
                'respondido_em' => Carbon::now(),
                'respondido_por' => $quem->id,
                'telefone' => $respostas['telefone'] ?? null,
                'aceita_dicas_whatsapp' => $dicas,
                'aceite_dicas_em' => $dicas ? Carbon::now() : null,
            ])->save();

            return $perfil;
        });
    }

    /** Pular é definitivo: a empresa fica com os padrões do cadastro. */
    public function pular(Tenant $tenant, User $quem): ?PerfilDeEmpresa
    {
        return DB::transaction(function () use ($tenant) {
            $perfil = $this->perfilTravado($tenant);
            if ($perfil !== null && ! $perfil->resolvido()) {
                $perfil->forceFill(['pulado_em' => Carbon::now()])->save();
            }

            return $perfil;
        });
    }

    private function perfilTravado(Tenant $tenant): ?PerfilDeEmpresa
    {
        return PerfilDeEmpresa::where('tenant_id', $tenant->id)->lockForUpdate()->first();
    }

    private function montarFunil(Tenant $tenant, string $ramo): void
    {
        $modelo = config("onboarding.ramos.{$ramo}");
        if (! is_array($modelo) || $modelo['estagios'] === null) {
            return;
        }

        $funil = Funil::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('is_default', true)->first();
        if ($funil === null) {
            return;
        }

        $antigos = Estagio::withoutGlobalScopes()->withTrashed()->where('funil_id', $funil->id)->pluck('id');
        $emUso = Usuario::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tenant->id)
            ->where(fn ($q) => $q->where('funil_id', $funil->id)->orWhereIn('estagio_id', $antigos))
            ->exists();
        if ($emUso) {
            return;
        }

        User::where('tenant_id', $tenant->id)->whereIn('kanban_default_estagio_id', $antigos)->update(['kanban_default_estagio_id' => null]);
        Estagio::withoutGlobalScopes()->withTrashed()->whereIn('id', $antigos)->forceDelete();

        $novos = [];
        foreach ($modelo['estagios'] as $i => $descricao) {
            $novos[] = [$descricao, Estagio::TIPO_ABERTO, self::CORES_ABERTAS[$i % count(self::CORES_ABERTAS)]];
        }
        $novos[] = [$modelo['ganho'], Estagio::TIPO_GANHO, self::COR_GANHO];
        $novos[] = [$modelo['perdido'], Estagio::TIPO_PERDIDO, self::COR_PERDIDO];

        foreach ($novos as $ordem => [$descricao, $tipo, $cor]) {
            $estagio = new Estagio;
            $estagio->funil_id = $funil->id;
            $estagio->descricao = $descricao;
            $estagio->ordem = $ordem + 1;
            $estagio->tipo = $tipo;
            $estagio->cor = $cor;
            $estagio->tenant_id = $tenant->id;
            $estagio->save();
        }
    }

    private function acrescentarMotivos(Tenant $tenant, string $ramo): void
    {
        $existentes = MotivoPerda::withoutGlobalScopes()->withTrashed()->where('tenant_id', $tenant->id);
        $ordem = (int) (clone $existentes)->max('ordem');

        foreach (config("onboarding.ramos.{$ramo}.motivos") ?? [] as $descricao) {
            if ((clone $existentes)->where('descricao', $descricao)->exists()) {
                continue;
            }
            $motivo = new MotivoPerda;
            $motivo->descricao = $descricao;
            $motivo->ordem = ++$ordem;
            $motivo->tenant_id = $tenant->id;
            $motivo->save();
        }
    }

    /** Na ordem dos canais escolhidos, sem repetir título, no máximo 4. */
    private function criarModelos(Tenant $tenant, User $quem, array $canais): void
    {
        $modelos = [];
        foreach ($canais as $canal) {
            $modelo = config("onboarding.canais.{$canal}.modelo");
            if ($modelo !== null) {
                $modelos[$modelo[0]] ??= $modelo[1];
            }
        }

        foreach (array_slice($modelos, 0, self::MAX_MODELOS, true) as $titulo => $prazo) {
            $existe = TarefaPadrao::withoutGlobalScopes()->where('tenant_id', $tenant->id)
                ->where('user_id', $quem->id)->where('titulo', $titulo)->exists();
            if ($existe) {
                continue;
            }
            $tarefa = new TarefaPadrao(['user_id' => $quem->id, 'titulo' => $titulo, 'prazo_dias' => $prazo]);
            $tarefa->tenant_id = $tenant->id;
            $tarefa->save();
        }
    }
}
