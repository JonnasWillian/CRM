import { beforeEach, describe, expect, it, vi } from 'vitest';

let props;
let atual = 'dashboard';
const patch = vi.fn(() => Promise.resolve({ data: {} }));
const visit = vi.fn();

vi.mock('axios', () => ({ default: { patch: (...a) => patch(...a) } }));
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props }),
    router: { visit: (...a) => visit(...a), on: () => () => {} },
}));

globalThis.route = (nome) => (nome === undefined ? { current: (n) => n === atual } : `/${nome}`);

function cenario(user, { permissoes = {}, equipe = false } = {}) {
    props = {
        auth: { user: { tour_estado: 'pendente', tour_versao: 'completa', tour_passo: null, ...user }, permissions: permissoes },
        empresa: { equipe_maior_que_um: equipe },
    };
}

async function useTour() {
    return (await import('@/Composables/useTour')).useTour();
}

/** Estado do passo a passo: módulo único, vindo de auth.user, gravado no servidor a cada passo. */
describe('useTour', () => {
    beforeEach(() => {
        vi.resetModules();
        patch.mockClear();
        visit.mockClear();
        atual = 'dashboard';
    });

    it('estado pendente: iniciar vai ao primeiro passo e grava', async () => {
        cenario({ tour_estado: 'pendente' });
        const t = await useTour();
        expect(t.pendente.value).toBe(true);
        expect(t.ativo.value).toBe(false);

        t.iniciar();

        expect(t.passo.value.chave).toBe('novo-lead');
        expect(t.ativo.value).toBe(true);
        expect(patch).toHaveBeenCalledWith('/api.tour.update', { acao: 'iniciar', passo: 'novo-lead' }, { silencioso: true });
        expect(visit).toHaveBeenCalledWith('/kanban'); // o primeiro passo é no Pipeline
    });

    it('estado vindo do servidor retoma o passo gravado', async () => {
        cenario({ tour_estado: 'em_andamento', tour_passo: 'lead-nome' });
        const t = await useTour();

        expect(t.ativo.value).toBe(true);
        expect(t.passo.value.chave).toBe('lead-nome');
        expect(t.indice.value).toBe(1);
    });

    it('avancar com o evento certo vai ao próximo; com outro evento não muda', async () => {
        cenario({ tour_estado: 'em_andamento', tour_passo: 'novo-lead' });
        atual = 'kanban';
        const t = await useTour();

        t.avancar('tarefa-criada');
        expect(t.passo.value.chave).toBe('novo-lead');
        expect(patch).not.toHaveBeenCalled();

        t.avancar('lead-modal-aberto');
        expect(t.passo.value.chave).toBe('lead-nome');
        expect(patch).toHaveBeenCalledWith('/api.tour.update', { acao: 'avancar', passo: 'lead-nome' }, { silencioso: true });
    });

    it('avanço por rota: abrir o perfil do lead no passo do cartão', async () => {
        cenario({ tour_estado: 'em_andamento', tour_passo: 'card-lead' });
        atual = 'kanban';
        const t = await useTour();

        atual = 'leads.show';
        t.aoNavegar();

        expect(t.passo.value.chave).toBe('nova-tarefa');
    });

    it('retomar: o passo do formulário volta ao botão Novo lead, no Pipeline', async () => {
        cenario({ tour_estado: 'em_andamento', tour_passo: 'lead-nome' });
        const t = await useTour();

        expect(t.retomar()).toBe(true);

        expect(t.passo.value.chave).toBe('novo-lead');
        expect(patch).toHaveBeenCalledWith('/api.tour.update', { acao: 'avancar', passo: 'novo-lead' }, { silencioso: true });
        expect(visit).toHaveBeenCalledWith('/kanban');
    });

    it('retomar: Nova tarefa fora do perfil volta ao cartão; no perfil, fica', async () => {
        cenario({ tour_estado: 'em_andamento', tour_passo: 'nova-tarefa' });
        atual = 'leads.show';
        const t = await useTour();
        expect(t.retomar()).toBe(false);
        expect(t.passo.value.chave).toBe('nova-tarefa');
        expect(visit).not.toHaveBeenCalled();

        atual = 'dashboard';
        expect(t.retomar()).toBe(true);
        expect(t.passo.value.chave).toBe('card-lead');
        expect(visit).toHaveBeenCalledWith('/kanban');
    });

    it('retomar: passo com tela própria abre essa tela', async () => {
        cenario({ tour_estado: 'em_andamento', tour_passo: 'kpis' });
        atual = 'kanban';
        const t = await useTour();

        t.retomar();

        expect(t.passo.value.chave).toBe('kpis');
        expect(visit).toHaveBeenCalledWith('/dashboard');
    });

    it('Salvar e abrir: já no perfil do lead, pula o passo do cartão', async () => {
        cenario({ tour_estado: 'em_andamento', tour_passo: 'lead-nome' });
        atual = 'leads.show';
        const t = await useTour();

        t.avancar('lead-criado');

        expect(t.passo.value.chave).toBe('nova-tarefa');
        expect(visit).not.toHaveBeenCalled();
    });

    it('passo equipe é pulado sem equipe ou sem permissão', async () => {
        cenario({ tour_estado: 'em_andamento', tour_passo: 'novo-lead' }, { equipe: true });
        expect((await useTour()).total.value).toBe(7);

        vi.resetModules();
        cenario({ tour_estado: 'em_andamento', tour_passo: 'novo-lead' }, { equipe: true, permissoes: { 'agentes.manage': true } });
        expect((await useTour()).total.value).toBe(8);

        vi.resetModules();
        cenario({ tour_estado: 'em_andamento', tour_passo: 'novo-lead' }, { equipe: false, permissoes: { 'agentes.manage': true } });
        expect((await useTour()).total.value).toBe(7);
    });

    it('cancelar desliga e grava cancelado', async () => {
        cenario({ tour_estado: 'em_andamento', tour_passo: 'tarefas' });
        const t = await useTour();

        t.cancelar();

        expect(t.ativo.value).toBe(false);
        expect(t.passo.value).toBe(null);
        expect(patch).toHaveBeenCalledWith('/api.tour.update', { acao: 'cancelar' }, { silencioso: true });
    });

    it('o último passo conclui', async () => {
        cenario({ tour_estado: 'em_andamento', tour_passo: 'fim' });
        const t = await useTour();

        t.concluir();

        expect(t.ativo.value).toBe(false);
        expect(patch).toHaveBeenCalledWith('/api.tour.update', { acao: 'concluir' }, { silencioso: true });
    });

    it('refazer começa a versão curta', async () => {
        cenario({ tour_estado: 'concluido', tour_versao: 'completa' }, { permissoes: { 'leads.view-all': true } });
        const t = await useTour();

        t.refazer();

        expect(t.ativo.value).toBe(true);
        expect(t.passo.value.chave).toBe('novo-lead');
        expect(t.passo.value.avanca).toBe('entendi'); // a curta só mostra
        expect(patch).toHaveBeenCalledWith('/api.tour.update', { acao: 'refazer', passo: 'novo-lead' }, { silencioso: true });

        t.proximo();
        expect(t.passo.value.chave).toBe('importar');
    });
});
