import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

let props;
const patch = vi.fn(() => Promise.resolve({ data: {} }));

vi.mock('axios', () => ({ default: { patch: (...a) => patch(...a) } }));
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props }),
    router: { visit: () => {}, on: () => () => {} },
}));

globalThis.route = (nome) => (nome === undefined ? { current: () => true } : `/${nome}`);

function cenario(passo) {
    props = { auth: { user: { tour_estado: 'em_andamento', tour_versao: 'completa', tour_passo: passo }, permissions: {} }, empresa: { equipe_maior_que_um: false } };
}

async function montar() {
    const { default: TourHost } = await import('@/Components/tour/TourHost.vue');
    const w = mount(TourHost, { attachTo: document.body });
    await vi.advanceTimersByTimeAsync(250);
    await flushPromises();
    return w;
}

const balao = () => document.querySelector('[data-tour-balao]');
const botao = (texto) => [...document.querySelectorAll('[data-tour-balao] button')].find((b) => b.textContent.trim() === texto);

/** O balão do passo a passo: aponta o alvo, X cancela tudo, Entendi avança, sem alvo fica no centro. */
describe('TourHost', () => {
    beforeEach(() => {
        vi.resetModules();
        vi.useFakeTimers();
        patch.mockClear();
        document.body.innerHTML = '';
    });
    afterEach(() => vi.useRealTimers());

    it('com alvo, mostra o balão com título e N de T', async () => {
        document.body.innerHTML = '<a data-tour="nav-tarefas.index">Tarefas</a>';
        cenario('tarefas');
        const w = await montar();

        expect(balao()).not.toBeNull();
        expect(balao().getAttribute('role')).toBe('dialog');
        expect(balao().textContent).toContain('Suas tarefas do dia ficam aqui.');
        expect(balao().textContent).toContain('5 de 7');
        expect(balao().hasAttribute('data-centralizado')).toBe(false);
        w.unmount();
    });

    it('X cancela o passo a passo inteiro', async () => {
        document.body.innerHTML = '<a data-tour="nav-tarefas.index">Tarefas</a>';
        cenario('tarefas');
        const w = await montar();

        document.querySelector('[data-tour-balao] button[aria-label="Fechar o passo a passo"]').click();
        await flushPromises();

        expect(patch).toHaveBeenCalledWith('/api.tour.update', { acao: 'cancelar' }, { silencioso: true });
        expect(balao()).toBeNull();
        w.unmount();
    });

    it('Esc também cancela', async () => {
        document.body.innerHTML = '<a data-tour="nav-tarefas.index">Tarefas</a>';
        cenario('tarefas');
        const w = await montar();

        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await flushPromises();

        expect(patch).toHaveBeenCalledWith('/api.tour.update', { acao: 'cancelar' }, { silencioso: true });
        w.unmount();
    });

    it('Esc já tratado por um modal ou menu não cancela o passo a passo', async () => {
        document.body.innerHTML = '<a data-tour="nav-tarefas.index">Tarefas</a>';
        cenario('tarefas');
        const w = await montar();

        const esc = new KeyboardEvent('keydown', { key: 'Escape', cancelable: true });
        esc.preventDefault(); // AppModal e AppDropdown fecham com Esc e marcam o evento
        window.dispatchEvent(esc);
        await flushPromises();

        expect(patch).not.toHaveBeenCalled();
        expect(balao()).not.toBeNull();
        w.unmount();
    });

    it('nos passos de mostrar, o foco vai para o balão', async () => {
        document.body.innerHTML = '<a data-tour="nav-tarefas.index">Tarefas</a>';
        cenario('tarefas');
        const w = await montar();

        expect(document.activeElement).toBe(balao());
        w.unmount();
    });

    it('com movimento reduzido, leva o alvo à vista sem animação', async () => {
        const anterior = window.matchMedia;
        window.matchMedia = (q) => ({ matches: q.includes('reduced-motion'), addEventListener: () => {}, removeEventListener: () => {} });
        document.body.innerHTML = '<a data-tour="nav-tarefas.index">Tarefas</a>';
        const rolar = vi.fn();
        document.querySelector('a').scrollIntoView = rolar;
        cenario('tarefas');
        const w = await montar();

        expect(rolar).toHaveBeenCalledWith({ block: 'center', behavior: 'auto' });
        window.matchMedia = anterior;
        w.unmount();
    });

    it('Entendi avança nos passos de mostrar', async () => {
        document.body.innerHTML = '<a data-tour="nav-tarefas.index">Tarefas</a>';
        cenario('tarefas');
        const w = await montar();

        botao('Entendi').click();
        await flushPromises();

        expect(patch).toHaveBeenCalledWith('/api.tour.update', { acao: 'avancar', passo: 'kpis' }, { silencioso: true });
        w.unmount();
    });

    it('passo de ação não tem Entendi', async () => {
        document.body.innerHTML = '<button data-tour="novo-lead">Novo lead</button>';
        cenario('novo-lead');
        const w = await montar();

        expect(balao().textContent).toContain('Cadastre seu primeiro lead.');
        expect(botao('Entendi')).toBeUndefined();
        w.unmount();
    });

    it('sem alvo, balão centralizado com Entendi', async () => {
        cenario('kpis');
        const w = await montar();
        expect(balao()).toBeNull(); // ainda procurando o alvo

        await vi.advanceTimersByTimeAsync(3200);
        await flushPromises();

        expect(balao()).not.toBeNull();
        expect(balao().hasAttribute('data-centralizado')).toBe(true);
        expect(botao('Entendi')).toBeDefined();
        w.unmount();
    });

    it('alvo dentro de um modal aberto: balão e contorno se posicionam em relação ao modal, à direita do campo', async () => {
        // O <dialog> com animação de transform vira a referência dos filhos `fixed` e corta o que passa da borda:
        // por isso, dentro dele, tudo é `absolute` e medido a partir do próprio modal.
        document.body.innerHTML = '<dialog open><div data-tour="lead-nome"><input></div></dialog>';
        const dialogo = document.querySelector('dialog');
        const campo = document.querySelector('[data-tour="lead-nome"]');
        dialogo.getBoundingClientRect = () => ({ top: 100, left: 500, width: 720, height: 500, right: 1220, bottom: 600 });
        campo.getBoundingClientRect = () => ({ top: 170, left: 530, width: 330, height: 40, right: 860, bottom: 210 });
        cenario('lead-nome');
        const w = await montar();

        const b = balao();
        expect(b.parentElement).toBe(dialogo);
        expect(b.classList.contains('absolute')).toBe(true);
        expect(b.classList.contains('fixed')).toBe(false);
        expect(b.style.left).toBe('380px'); // borda direita do campo no modal (360) + folga 8 + margem 12
        expect(b.style.top).toBe('62px'); // topo do campo no modal (70) - folga 8

        const anel = dialogo.querySelector('[data-tour-anel]');
        expect(anel.style.top).toBe('66px');
        expect(anel.style.left).toBe('26px');
        w.unmount();
    });

    it('o último passo tem Concluir, que encerra', async () => {
        cenario('fim');
        const w = await montar();

        botao('Concluir').click();
        await flushPromises();

        expect(patch).toHaveBeenCalledWith('/api.tour.update', { acao: 'concluir' }, { silencioso: true });
        w.unmount();
    });
});
