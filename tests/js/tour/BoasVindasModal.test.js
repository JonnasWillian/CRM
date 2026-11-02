import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

let props;
const patch = vi.fn(() => Promise.resolve({ data: {} }));

vi.mock('axios', () => ({ default: { patch: (...a) => patch(...a) } }));
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props }),
    router: { visit: () => {}, on: () => () => {} },
}));

globalThis.route = (nome) => (nome === undefined ? { current: () => true } : `/${nome}`);

async function montar(estado) {
    props = { app: { nome: 'UserFlow' }, auth: { user: { name: 'Ana Souza', tour_estado: estado, tour_versao: 'completa', tour_passo: null }, permissions: {} }, empresa: { equipe_maior_que_um: false } };
    const { default: BoasVindasModal } = await import('@/Components/tour/BoasVindasModal.vue');
    return mount(BoasVindasModal, { attachTo: document.body });
}

const botao = (w, texto) => w.findAll('button').find((b) => b.text().trim() === texto);

/** Boas-vindas do primeiro acesso: Vamos lá! começa o passo a passo; recusar cancela. */
describe('BoasVindasModal', () => {
    beforeEach(() => {
        vi.resetModules();
        patch.mockClear();
    });

    it('abre quando pendente; Vamos lá! inicia', async () => {
        const w = await montar('pendente');

        expect(w.text()).toContain('Bem-vindo ao UserFlow, Ana!');
        await botao(w, 'Vamos lá!').trigger('click');
        await flushPromises();

        expect(patch).toHaveBeenCalledWith('/api.tour.update', { acao: 'iniciar', passo: 'novo-lead' }, { silencioso: true });
        expect(w.text()).not.toContain('Bem-vindo ao UserFlow');
        w.unmount();
    });

    it('Prefiro explorar sozinho cancela', async () => {
        const w = await montar('pendente');

        await botao(w, 'Prefiro explorar sozinho').trigger('click');
        await flushPromises();

        expect(patch).toHaveBeenCalledWith('/api.tour.update', { acao: 'cancelar' }, { silencioso: true });
        w.unmount();
    });

    it('clicar fora do modal não cancela: só o X, o Esc ou o botão', async () => {
        const w = await montar('pendente');
        const { default: AppModal } = await import('@/Components/ui/AppModal.vue');
        expect(w.findComponent(AppModal).props('persistente')).toBe(true);
        w.unmount();
    });

    it('não abre para quem já concluiu ou cancelou', async () => {
        const w = await montar('concluido');
        expect(w.text()).not.toContain('Bem-vindo');
        w.unmount();
    });
});
