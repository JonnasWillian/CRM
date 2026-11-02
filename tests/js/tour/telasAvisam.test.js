import { flushPromises, mount } from '@vue/test-utils';
import { reactive } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';

let props;
const patch = vi.fn(() => Promise.resolve({ data: {} }));
const post = vi.fn();

vi.mock('axios', () => ({ default: { patch: (...a) => patch(...a), post: (...a) => post(...a) } }));
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props }),
    useForm: (dados) => reactive({ ...dados, errors: {}, processing: false, reset() {}, clearErrors() {}, transform() { return this; }, post: (_url, opcoes) => opcoes?.onSuccess?.() }),
    router: { visit: () => {}, on: () => () => {}, post: () => {} },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));
vi.mock('@/Composables/usePerda', () => ({ usePerda: () => ({ pedirPerda: () => {} }) }));
vi.mock('@/Composables/useConfirm', () => ({ useConfirm: () => ({ confirmar: () => Promise.resolve(true) }) }));

// O cadastro e a tarefa acontecem no Pipeline e no perfil; aqui, a tela atual é o Pipeline.
globalThis.route = (nome) => (nome === undefined ? { current: (n) => n === 'kanban' } : `/${nome}`);

function cenario(passo) {
    props = { auth: { user: { id: 1, tour_estado: 'em_andamento', tour_versao: 'completa', tour_passo: passo }, permissions: {} }, empresa: { equipe_maior_que_um: false, origens: [] }, limites: {} };
}

/** As telas avisam o passo a passo: abrir o modal de lead, salvar o lead, salvar a tarefa. */
describe('Telas avisam o passo a passo', () => {
    beforeEach(() => {
        vi.resetModules();
        patch.mockClear();
    });

    it('LeadFormModal: abrir avança do botão para o campo nome, salvar avança para o cartão', async () => {
        cenario('novo-lead');
        const { useTour } = await import('@/Composables/useTour');
        const { default: LeadFormModal } = await import('@/Components/leads/LeadFormModal.vue');
        const tour = useTour();
        const w = mount(LeadFormModal, {
            props: { aberto: false, funis: [{ id: 1, nome: 'Vendas', is_default: true }], estagios: [{ id: 1, funil_id: 1, descricao: 'Lead', tipo: 'aberto', ordem: 1 }] },
            global: { mocks: { route: globalThis.route } },
            attachTo: document.body,
        });

        await w.setProps({ aberto: true });
        expect(tour.passo.value.chave).toBe('lead-nome');
        expect(w.find('[data-tour="lead-nome"]').exists()).toBe(true);
        expect(w.find('[data-tour="lead-nome"] input[type="email"]').exists()).toBe(true); // nome e e-mail juntos: o balão não cobre nenhum dos dois

        await w.find('form').trigger('submit');
        await flushPromises();
        expect(tour.passo.value.chave).toBe('card-lead');
        w.unmount();
    });

    it('TarefaFormModal: salvar avança do botão Nova tarefa para o menu Tarefas', async () => {
        cenario('nova-tarefa');
        post.mockResolvedValue({ data: { id: 9 } });
        const { useTour } = await import('@/Composables/useTour');
        const { default: TarefaFormModal } = await import('@/Components/leads/TarefaFormModal.vue');
        const tour = useTour();
        const w = mount(TarefaFormModal, { props: { aberto: true, leadId: 5, tarefa: null }, global: { mocks: { route: globalThis.route } }, attachTo: document.body });

        await w.find('input').setValue('Ligar amanhã');
        await w.find('form').trigger('submit');
        await flushPromises();

        expect(tour.passo.value.chave).toBe('tarefas');
        w.unmount();
    });
});
