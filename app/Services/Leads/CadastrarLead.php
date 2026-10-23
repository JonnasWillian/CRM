<?php

namespace App\Services\Leads;

use App\Models\Estagio;
use App\Models\Funil;
use App\Models\User;
use App\Models\Usuario;
use App\Services\Perdas\AplicarTransicao;

/**
 * Cadastro de lead, o mesmo para o modal da grade (web) e o quick-add do
 * Pipeline (API): funil padrão e primeiro estágio aberto quando não vêm,
 * responsável = quem cadastra (ou o escolhido por quem tem leads.view-all)
 * e perda registrada se o lead já nasce num estágio perdido.
 */
class CadastrarLead
{
    public function __construct(private readonly AplicarTransicao $transicao) {}

    /**
     * @param  array<string, mixed>  $dados  validados por UsuarioRequest
     * @param  array{motivo_perda_id: int|null, observacao: string|null}  $perda
     */
    public function __invoke(array $dados, array $perda, User $autor): Usuario
    {
        $responsavel = (int) ($dados['user_id'] ?? 0);
        $dados['user_id'] = $responsavel && $autor->can('leads.view-all') ? $responsavel : $autor->id;

        if (empty($dados['funil_id'])) {
            $dados['funil_id'] = Funil::padrao()?->id;
        }

        if (empty($dados['estagio_id']) && ! empty($dados['funil_id'])) {
            $dados['estagio_id'] = Estagio::where('funil_id', $dados['funil_id'])->aberto()->orderBy('ordem')->orderBy('id')->value('id');
        }

        app(\App\Services\Assinaturas\Assinaturas::class)->verificarLimite($autor->tenant_id, 'leads');

        $lead = new Usuario;
        ($this->transicao)($lead, $dados, $perda);

        return $lead;
    }
}
