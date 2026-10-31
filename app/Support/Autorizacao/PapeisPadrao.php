<?php

namespace App\Support\Autorizacao;

/**
 * O contrato de papéis e permissões do sistema. A migration
 * 2026_09_22_100008 criou exatamente isto (e fica congelada, como toda
 * migration); o PapeisEPermissoesSeeder restaura a partir daqui. O teste
 * PapeisEPermissoesSeederTest compara os dois: mudar um sem o outro quebra.
 *
 * Papéis são globais (`tenant_id = null`); o que é por empresa é a
 * atribuição, em `model_has_roles`.
 */
final class PapeisPadrao
{
    public const PERMISSOES = [
        'leads.view-all',
        'leads.manage',
        'projetos.manage',
        'tarefas.manage',
        'configuracoes.manage',
        'agentes.manage',
    ];

    /** @var array<string, list<string>> */
    public const PAPEIS = [
        'admin' => self::PERMISSOES,
        'gestor' => ['leads.view-all', 'leads.manage', 'projetos.manage', 'tarefas.manage', 'configuracoes.manage'],
        'vendedor' => ['leads.manage', 'projetos.manage', 'tarefas.manage'],
    ];
}
