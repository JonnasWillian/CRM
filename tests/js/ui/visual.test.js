import { mount } from '@vue/test-utils';
import { reactive } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';

let paginaProps = { limites: {} };
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: paginaProps }),
    useForm: (dados) => reactive({ ...dados, errors: {}, processing: false, reset() {}, clearErrors() {}, post: () => {} }),
    router: { post: () => {}, visit: () => {}, on: () => () => {} },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));
vi.mock('@/Composables/usePerda', () => ({ usePerda: () => ({ pedirPerda: () => {} }) }));

/** Peças do visual novo que têm comportamento: tema no topo, rótulo flutuante, destaque do título. */
describe('AlternarTema (um ícone que alterna, nas telas sem login)', () => {
    beforeEach(() => {
        vi.resetModules();
        localStorage.clear();
        document.documentElement.removeAttribute('data-theme');
        window.matchMedia = vi.fn().mockImplementation(() => ({ matches: false, addEventListener: vi.fn(), removeEventListener: vi.fn() }));
    });

    const montar = async () => {
        const { default: AlternarTema } = await import('@/Components/ui/AlternarTema.vue');
        return mount(AlternarTema, { attachTo: document.body });
    };
    const temIcone = (w, nome) => w.find(`svg.lucide-${nome}-icon, svg.lucide-${nome}`).exists();

    it('um botão só: no claro mostra o sol e oferece o escuro', async () => {
        const w = await montar();

        expect(w.findAll('button')).toHaveLength(1);
        expect(temIcone(w, 'sun')).toBe(true);
        expect(temIcone(w, 'moon')).toBe(false);
        expect(w.find('button').attributes('aria-label')).toBe('Usar tema escuro');
        w.unmount();
    });

    it('clicar troca o tema e o ícone; clicar de novo volta', async () => {
        const w = await montar();

        await w.find('button').trigger('click');
        expect(document.documentElement.dataset.theme).toBe('escuro');
        expect(localStorage.getItem('ui.tema')).toBe('escuro');
        expect(temIcone(w, 'moon')).toBe(true);
        expect(temIcone(w, 'sun')).toBe(false);
        expect(w.find('button').attributes('aria-label')).toBe('Usar tema claro');

        await w.find('button').trigger('click');
        expect(document.documentElement.dataset.theme).toBe('claro');
        expect(temIcone(w, 'sun')).toBe(true);
        w.unmount();
    });

    it('seguindo o sistema escuro, já mostra a lua', async () => {
        window.matchMedia = vi.fn().mockImplementation(() => ({ matches: true, addEventListener: vi.fn(), removeEventListener: vi.fn() }));
        const w = await montar();

        expect(temIcone(w, 'moon')).toBe(true);
        expect(w.find('button').attributes('aria-label')).toBe('Usar tema claro');
        w.unmount();
    });
});

describe('FormField flutuante (telas de entrada)', () => {
    it('o rótulo fica dentro do campo, depois do input, ligado pelo id', async () => {
        const { default: FormField } = await import('@/Components/ui/FormField.vue');
        const { default: AppInput } = await import('@/Components/ui/AppInput.vue');
        const w = mount({
            components: { FormField, AppInput },
            template: '<FormField rotulo="E-mail" flutuante erro="Informe o e-mail."><AppInput placeholder="voce@empresa.com" /></FormField>',
        }, { global: { mocks: { route: () => '' } } });

        const caixa = w.find('[data-flutuante]');
        expect(caixa.exists()).toBe(true);
        const input = caixa.find('input');
        const rotulo = caixa.find('label');
        expect(rotulo.text()).toBe('E-mail');
        expect(rotulo.attributes('for')).toBe(input.attributes('id'));
        expect(input.element.compareDocumentPosition(rotulo.element) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
        expect(w.text()).toContain('Informe o e-mail.');
    });

    it('sem flutuante, o rótulo continua acima do campo', async () => {
        const { default: FormField } = await import('@/Components/ui/FormField.vue');
        const w = mount(FormField, { props: { rotulo: 'Nome' }, slots: { default: '<input id="x" />' } });
        expect(w.find('[data-flutuante]').exists()).toBe(false);
        expect(w.find('label').element.compareDocumentPosition(w.find('input').element) & Node.DOCUMENT_POSITION_FOLLOWING).toBeTruthy();
    });
});

describe('PainelDeEntrada: destaque do título', () => {
    it('o trecho depois da última vírgula ganha destaque, sem mudar o texto', async () => {
        const { default: PainelDeEntrada } = await import('@/Components/auth/PainelDeEntrada.vue');
        const w = mount(PainelDeEntrada, { props: { titulo: 'O que você já fatura e o que ainda vai entrar, numa tela só.' } });

        expect(w.find('h2').text()).toBe('O que você já fatura e o que ainda vai entrar, numa tela só.');
        expect(w.find('h2 [data-destaque]').text()).toBe('numa tela só.');
    });

    it('título sem vírgula fica inteiro, sem destaque', async () => {
        const { default: PainelDeEntrada } = await import('@/Components/auth/PainelDeEntrada.vue');
        const w = mount(PainelDeEntrada, { props: { titulo: 'Em cinco minutos' } });

        expect(w.find('h2').text()).toBe('Em cinco minutos');
        expect(w.find('h2 [data-destaque]').exists()).toBe(false);
    });
});

describe('LeadFormModal: rótulo e origens da empresa', () => {
    it('mostra o rótulo do nome e as origens da empresa', async () => {
        paginaProps = { auth: { user: { id: 1 } }, empresa: { rotulo_nome_lead: 'Nome da empresa', origens: ['Indicação', 'Outro'] }, limites: {} };
        const { default: LeadFormModal } = await import('@/Components/leads/LeadFormModal.vue');
        const w = mount(LeadFormModal, {
            props: { aberto: true, funis: [{ id: 1, nome: 'Vendas', is_default: true }], estagios: [{ id: 1, funil_id: 1, descricao: 'Lead', tipo: 'aberto', ordem: 1 }] },
            global: { mocks: { route: () => '' } },
            attachTo: document.body,
        });

        expect(w.findAll('label').map((l) => l.text())).toContain('Nome da empresa *');
        const origem = w.findAll('select').find((s) => s.findAll('option').some((o) => o.text() === 'Indicação'));
        expect(origem?.findAll('option').map((o) => o.text())).toEqual(['Selecione', 'Indicação', 'Outro']);
        w.unmount();
        paginaProps = { limites: {} };
    });
});
