/**
 * Os passos do passo a passo do primeiro acesso (spec
 * docs/superpowers/specs/2026-11-01-onboarding-personalizado-design.md).
 *
 * Cada passo:
 *  - `chave`: o que fica gravado em users.tour_passo;
 *  - `rota?`: nome Ziggy; ao entrar no passo em outra tela, o tour navega para lá;
 *  - `alvo`: valor de `data-tour` no elemento apontado; null = balão no centro;
 *  - `avanca`: { evento } (a tela avisa: useTour().avancar(evento)),
 *              { rota } (chegou nessa rota), 'entendi' ou 'concluir';
 *  - `quando?`: (ctx) => boolean, com ctx = { pode, equipe }; falso pula o passo;
 *  - `retomar?`: (estaEm) => chave|null, para passos que dependem de algo que não
 *    sobrevive a recarregar a página (um modal aberto, o perfil do lead criado):
 *    ao voltar, ou sem alvo na tela, recomeça do passo que leva até lá.
 *
 * A versão completa pede ações de verdade (cadastrar um lead e uma tarefa);
 * a curta, para quem já usou CRM, convidados e "Refazer", só mostra.
 */

const tarefas = {
    chave: 'tarefas',
    alvo: 'nav-tarefas.index',
    titulo: 'Suas tarefas do dia ficam aqui.',
    texto: 'As de hoje e as atrasadas, num lugar só.',
    avanca: 'entendi',
};

const kpis = {
    chave: 'kpis',
    rota: 'dashboard',
    alvo: 'kpis',
    titulo: 'Aqui você vê o que já fatura e o que ainda vai entrar.',
    texto: 'Leads ativos, ganhos, perdidos e a taxa de ganho do período escolhido.',
    avanca: 'entendi',
};

const equipe = {
    chave: 'equipe',
    alvo: 'nav-configuracoes.equipe',
    titulo: 'Convide sua equipe.',
    texto: 'Cada vendedor vê a própria carteira; você vê todas.',
    avanca: 'entendi',
    quando: (ctx) => ctx.equipe && ctx.pode('agentes.manage'),
};

const fim = {
    chave: 'fim',
    alvo: null,
    titulo: 'Pronto! Seu CRM está montado.',
    texto: 'Se quiser rever, o passo a passo fica no menu da sua conta.',
    avanca: 'concluir',
};

export const PASSOS = {
    completa: [
        {
            chave: 'novo-lead',
            rota: 'kanban',
            alvo: 'novo-lead',
            titulo: 'Cadastre seu primeiro lead.',
            texto: 'Clique aqui para adicionar um contato ao funil.',
            avanca: { evento: 'lead-modal-aberto' },
        },
        {
            chave: 'lead-nome',
            alvo: 'lead-nome',
            titulo: 'Experimente cadastrar "Cliente teste".',
            texto: 'Preencha o nome e um e-mail qualquer (como teste@exemplo.com) e salve. Você pode apagar depois.',
            avanca: { evento: 'lead-criado' },
            retomar: () => 'novo-lead', // o modal fechou (recarregou, Esc): de volta ao botão
        },
        {
            chave: 'card-lead',
            rota: 'kanban',
            alvo: 'card-lead',
            titulo: 'Este é o seu lead no funil.',
            texto: 'Quando a negociação avançar, arraste o cartão para o próximo estágio. Clique no nome para abrir.',
            avanca: { rota: 'leads.show' },
        },
        {
            chave: 'nova-tarefa',
            alvo: 'nova-tarefa',
            titulo: 'Agende o próximo contato.',
            texto: 'Tarefas lembram você de não deixar ninguém esperando.',
            avanca: { evento: 'tarefa-criada' },
            retomar: (estaEm) => (estaEm('leads.show') ? null : 'card-lead'), // fora do perfil: de volta ao cartão
        },
        tarefas,
        kpis,
        equipe,
        fim,
    ],
    curta: [
        {
            chave: 'novo-lead',
            rota: 'kanban',
            alvo: 'novo-lead',
            titulo: 'Seus leads entram por aqui.',
            texto: 'Cadastre um lead pelo botão ou arraste os cartões entre os estágios do funil.',
            avanca: 'entendi',
        },
        {
            chave: 'importar',
            rota: 'leads.index',
            alvo: 'importar',
            titulo: 'Traga sua planilha.',
            texto: 'Importe seus contatos de um arquivo CSV em poucos passos.',
            avanca: 'entendi',
            quando: (ctx) => ctx.pode('leads.view-all'),
        },
        tarefas,
        kpis,
        equipe,
        fim,
    ],
};
