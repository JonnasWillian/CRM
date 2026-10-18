import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, ref } from 'vue';

const paginaProps = ref({
    auth: {
        user: { name: 'Ana Souza', email: 'ana@acme.com' },
        permissions: { 'agentes.manage': true, 'configuracoes.manage': true, 'leads.view-all': true, 'leads.manage': true },
        roles: ['admin'],
    },
    flash: { sucesso: null, erro: null },
});

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ get props() { return paginaProps.value; } }),
    Link: { props: ['href', 'method', 'as'], template: '<a :href="href"><slot /></a>' },
    router: { on: () => () => {}, visit: vi.fn() },
}));

const rotas = new Set(['dashboard', 'kanban', 'configuracoes.modelosTarefa', 'relatorios.perdas', 'configuracoes.funis', 'configuracoes.motivosPerda', 'profile.edit', 'logout']);
// Como o Ziggy: route('nome') devolve a URL; route() devolve o roteador com current()/has().
const roteador = { current: (nome) => nome === 'dashboard', has: (nome) => rotas.has(nome) };
globalThis.route = (nome) => (nome === undefined ? roteador : `/${nome}`);

const { default: AuthenticatedLayout } = await import('@/Layouts/AuthenticatedLayout.vue');

// ZiggyVue registra `route` como propriedade global do app; aqui, por mocks.
const montar = (opcoes = {}) => mount(AuthenticatedLayout, { ...opcoes, attachTo: document.body, global: { mocks: { route: globalThis.route } } });
const { useToast } = await import('@/Composables/useToast');

describe('AuthenticatedLayout', () => {
    beforeEach(() => {
        useToast().limpar();
        localStorage.clear();
    });

    it('mostra a navegação permitida, o usuário e o conteúdo', () => {
        const w = montar({ slots: { default: '<p>conteúdo</p>' } });
        const rotulos = w.findAll('nav a').map((a) => a.text());
        expect(rotulos).toContain('Dashboard');
        expect(rotulos).toContain('Pipeline');
        expect(rotulos).toContain('Funis');
        expect(rotulos).not.toContain('Leads'); // rota ainda não existe
        expect(w.text()).toContain('Ana Souza');
        expect(w.find('main').text()).toContain('conteúdo');
        w.unmount();
    });

    it('a gaveta mobile abre pelo botão e fecha com Esc', async () => {
        const w = montar();
        const burger = w.find('button[aria-label="Abrir menu"]');
        await burger.trigger('click');
        await nextTick();
        const gaveta = w.find('dialog');
        expect(gaveta.exists()).toBe(true);
        expect(gaveta.attributes('open')).toBeDefined();
        await gaveta.trigger('keydown', { key: 'Escape' });
        await nextTick();
        expect(w.find('dialog').exists()).toBe(false);
        w.unmount();
    });

    it('colapsar a barra lateral persiste a preferência', async () => {
        const w = montar();
        await w.find('button[aria-label="Recolher menu"]').trigger('click');
        expect(JSON.parse(localStorage.getItem('ui.sidebar.colapsada'))).toBe(true);
        expect(w.find('aside').attributes('data-colapsada')).toBe('true');
        w.unmount();
    });

    it('flash de sucesso vira toast', async () => {
        const w = montar();
        paginaProps.value = { ...paginaProps.value, flash: { sucesso: 'Lead cadastrado.', erro: null } };
        await nextTick();
        await nextTick();
        expect(useToast().toasts.value.map((t) => t.mensagem)).toContain('Lead cadastrado.');
        w.unmount();
    });
});
