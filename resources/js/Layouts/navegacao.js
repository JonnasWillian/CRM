import {
    BarChart3,
    CheckSquare,
    CircleX,
    GitBranch,
    LayoutDashboard,
    LayoutTemplate,
    SquareKanban,
    Trash2,
    UserCog,
    Users,
} from 'lucide-vue-next';

/**
 * A navegação, declarada UMA vez, para a barra lateral e para a gaveta
 * mobile. Cada item diz a rota (nome do Ziggy), o rótulo, o ícone e,
 * quando restrito, a permissão.
 *
 * `existe(rota)` esconde itens de páginas que ainda não nasceram: a lista
 * já é a final, e cada fase que cria uma página só precisa registrar a
 * rota para o item aparecer.
 *
 * @param {{ pode: (permissao: string) => boolean, existe: (rota: string) => boolean }} ctx
 */
export function gruposDeNavegacao({ pode, existe }) {
    const grupos = [
        {
            itens: [
                { rota: 'dashboard', rotulo: 'Dashboard', icone: LayoutDashboard },
                { rota: 'leads.index', rotulo: 'Leads', icone: Users },
                { rota: 'kanban', rotulo: 'Pipeline', icone: SquareKanban },
                { rota: 'tarefas.index', rotulo: 'Tarefas', icone: CheckSquare },
            ],
        },
        {
            titulo: 'Análise',
            itens: [
                // Até o hub /relatorios existir, o item leva ao relatório de perdas.
                { rota: existe('relatorios.index') ? 'relatorios.index' : 'relatorios.perdas', rotulo: 'Relatórios', icone: BarChart3, permissao: 'leads.view-all', ativaEm: ['relatorios.*'] },
            ],
        },
        {
            titulo: 'Configurações',
            itens: [
                { rota: 'configuracoes.funis', rotulo: 'Funis', icone: GitBranch, permissao: 'configuracoes.manage' },
                { rota: 'configuracoes.motivosPerda', rotulo: 'Motivos de perda', icone: CircleX, permissao: 'configuracoes.manage' },
                { rota: existe('configuracoes.modelosTarefa') ? 'configuracoes.modelosTarefa' : 'modelosTarefa', rotulo: 'Modelos de tarefa', icone: LayoutTemplate },
                { rota: 'configuracoes.equipe', rotulo: 'Equipe', icone: UserCog, permissao: 'agentes.manage' },
            ],
        },
        {
            titulo: 'Sistema',
            itens: [{ rota: 'lixeira', rotulo: 'Lixeira', icone: Trash2 }],
        },
    ];

    return grupos
        .map((g) => ({ ...g, itens: g.itens.filter((i) => (!i.permissao || pode(i.permissao)) && existe(i.rota)) }))
        .filter((g) => g.itens.length > 0);
}
