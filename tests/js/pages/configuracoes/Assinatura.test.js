import { flushPromises, mount } from '@vue/test-utils';
import { reactive } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { formatBRL } from '@/utils/formatadores';

const post = vi.fn();
const formPost = vi.fn();
const get = vi.fn();
const confirmar = vi.fn().mockResolvedValue(true);

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { auth: { permissions: {} } } }),
    useForm: (dados) => reactive({ ...dados, errors: {}, processing: false, post: (...a) => formPost(...a) }),
    router: { post: (...args) => post(...args) },
    Head: { template: '<div />' },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));
vi.mock('axios', () => ({ default: { get: (...a) => get(...a) } }));
vi.mock('@/Composables/useConfirm', () => ({ useConfirm: () => ({ confirmar }) }));

globalThis.route = (nome) => `/${nome}`;

const { default: Assinatura } = await import('@/Pages/Configuracoes/Assinatura.vue');

const plano = (extra) => ({ descricao: 'd', limite: '', inclui: [], destaque: false, excessos: [], limites: { usuarios: null, funis: null, leads: null }, ...extra });
const PLANOS = [
    plano({ chave: 'inicial', nome: 'Inicial', preco_mensal: 49, preco_anual: 39, limites: { usuarios: 3, funis: 1, leads: 2000 }, excessos: ['O plano Inicial permite até 3 usuários ativos e a empresa tem 4. Desative 1 em Configurações › Equipe ou escolha outro plano.'] }),
    plano({ chave: 'profissional', nome: 'Profissional', preco_mensal: 89, preco_anual: 69, destaque: true }),
    plano({ chave: 'empresa', nome: 'Empresa', preco_mensal: 139, preco_anual: 109, usuarios_minimos: 5 }),
];
const assinatura = (extra = {}) => ({
    status: 'teste', plano: 'profissional', plano_nome: 'Profissional', ciclo: null, assentos: null, dias_restantes: 10, teste_termina_em: '2026-11-03', vigente_ate: null,
    bloqueada: false, carencia_ate: null, somente_leitura_desde: null, suspende_em: null, suspensa: false, renovacao_cancelada: false,
    limites: { usuarios: null, funis: null, leads: null }, uso: { usuarios: 4, funis: 1, leads: 10 }, convites_pendentes: 0, fatura_pendente: null, ...extra,
});

function montar(props = {}) {
    return mount(Assinatura, {
        props: { assinatura: assinatura(), planos: PLANOS, usuariosCobrados: 4, ocupados: 4, faturaPendente: null, faturas: [], pagamento: { instrucoes: null, email: 'comercial@crm.test', whatsapp: null }, diasDeTeste: 14, ...props },
        global: { mocks: { route: globalThis.route }, stubs: { AuthenticatedLayout: { template: '<div><slot /></div>' } } },
    });
}
const botaoDo = (w, chave) => w.find(`[data-plano="${chave}"] button`);

describe('Configurações › Assinatura', () => {
    beforeEach(() => {
        [post, formPost, get, confirmar].forEach((f) => f.mockClear());
        confirmar.mockResolvedValue(true);
    });

    it('plano em que o uso não cabe explica o motivo e não deixa contratar', () => {
        const w = montar();
        expect(w.find('[data-plano="inicial"]').text()).toContain('a empresa tem 4');
        expect(botaoDo(w, 'inicial').text()).toBe('Não cabe no uso atual');
        expect(botaoDo(w, 'inicial').attributes('disabled')).toBeDefined();
    });

    it('mostra o total para os usuários escolhidos, no ciclo escolhido, com o mínimo de cada plano', async () => {
        const w = montar();
        expect(w.find('[data-plano="profissional"]').text()).toContain(`4 usuários: ${formatBRL(3312)} por ano`);
        expect(w.find('[data-plano="empresa"]').text()).toContain(`5 usuários: ${formatBRL(6540)} por ano`);
        await w.find('[role="radio"]').trigger('click'); // mensal
        expect(w.find('[data-plano="profissional"]').text()).toContain(`4 usuários: ${formatBRL(356)} por mês`);
    });

    it('o número de usuários começa no que a empresa ocupa, não desce abaixo disso e respeita o limite do plano', async () => {
        const w = montar({ ocupados: 2, planos: [plano({ ...PLANOS[0], excessos: [] }), PLANOS[1]] });
        const campo = w.find('input[data-assentos]');
        expect(campo.element.value).toBe('2');
        expect(campo.attributes('min')).toBe('2');

        await campo.setValue('6');
        expect(w.find('[data-plano="profissional"]').text()).toContain(`6 usuários: ${formatBRL(4968)} por ano`);
        expect(w.find('[data-plano="inicial"]').text()).toContain('O plano Inicial permite até 3 usuários.');
        expect(botaoDo(w, 'inicial').attributes('disabled')).toBeDefined();
    });

    it('contratar confere a prévia do servidor e confirma com crédito e valor final antes de gerar a fatura', async () => {
        get.mockResolvedValue({ data: { motivo: 'troca', usuarios: 4, bruto: 3312, credito: 1000, valor: 2312, vence_em: '2026-10-27' } });
        const w = montar({ assinatura: assinatura({ status: 'ativa', plano: 'inicial', plano_nome: 'Inicial', ciclo: 'anual', assentos: 4, vigente_ate: '2027-04-24' }) });

        await botaoDo(w, 'profissional').trigger('click');
        await flushPromises();

        expect(get).toHaveBeenCalledWith('/configuracoes.assinatura.previa', { params: { plano: 'profissional', ciclo: 'anual', assentos: 4 } });
        const pedido = confirmar.mock.calls[0][0];
        expect(pedido.titulo).toBe('Trocar para o plano Profissional?');
        expect(pedido.mensagem).toContain(`menos ${formatBRL(1000)} de crédito`);
        expect(pedido.mensagem).toContain(formatBRL(2312));
        expect(pedido.rotuloConfirmar).toBe('Gerar fatura');
        expect(formPost).toHaveBeenCalledWith('/configuracoes.assinatura.escolher', expect.any(Object));
    });

    it('no plano atual, renovar só quando nada muda; com outros usuários ou ciclo, é atualizar', async () => {
        const w = montar({ assinatura: assinatura({ status: 'ativa', ciclo: 'anual', assentos: 4, vigente_ate: '2027-10-23' }) });
        expect(botaoDo(w, 'profissional').text()).toBe('Renovar agora');
        await w.find('input[data-assentos]').setValue('5');
        expect(botaoDo(w, 'profissional').text()).toBe('Atualizar plano');
    });

    it('troca sem crédito não fala em crédito', async () => {
        get.mockResolvedValue({ data: { motivo: 'troca', usuarios: 3, bruto: 2484, credito: 0, valor: 2484, vence_em: '2026-10-27' } });
        const w = montar({ assinatura: assinatura({ status: 'ativa', ciclo: 'anual', assentos: 1, vigente_ate: '2027-10-23' }) });
        await botaoDo(w, 'profissional').trigger('click');
        await flushPromises();
        expect(confirmar.mock.calls[0][0].mensagem).not.toContain('crédito');
        expect(confirmar.mock.calls[0][0].mensagem).toContain(`= ${formatBRL(2484)}.`);
    });

    it('contratação no teste promete ativação só depois do pagamento', async () => {
        get.mockResolvedValue({ data: { motivo: 'contratacao', usuarios: 4, bruto: 3312, credito: 0, valor: 3312, vence_em: '2026-11-03' } });
        const w = montar();
        await botaoDo(w, 'profissional').trigger('click');
        await flushPromises();
        const pedido = confirmar.mock.calls[0][0];
        expect(pedido.titulo).toBe('Gerar fatura do plano Profissional?');
        expect(pedido.mensagem).toContain('ativado assim que o pagamento for confirmado');
    });

    it('quando a escolha não pode ser feita agora, mostra o motivo do servidor e não confirma nada', async () => {
        get.mockRejectedValue({ response: { status: 422, data: { message: 'x', errors: { plano: ['Trocar para um plano menor vale na renovação.'] } } } });
        const w = montar();
        await botaoDo(w, 'profissional').trigger('click');
        await flushPromises();
        expect(confirmar).not.toHaveBeenCalled();
        expect(w.find('[role="alert"]').text()).toBe('Trocar para um plano menor vale na renovação.');
    });

    it('fatura pendente mostra valor, crédito, vencimento, como pagar e o contato', () => {
        const w = montar({
            faturaPendente: { id: 7, plano_nome: 'Profissional', ciclo: 'anual', usuarios: 4, credito: 1000, valor: 2312, vence_em: '2026-11-03', atrasada: false, status: 'pendente' },
            pagamento: { instrucoes: 'Pix: financeiro@crm.test', email: 'comercial@crm.test', whatsapp: '5511999998888' },
        });
        const bloco = w.find('[data-fatura-pendente]');
        expect(bloco.text()).toContain(formatBRL(2312));
        expect(bloco.text()).toContain(`Inclui ${formatBRL(1000)} de crédito do plano atual.`);
        expect(bloco.text()).toContain('03/11/2026');
        expect(bloco.text()).toContain('Pix: financeiro@crm.test');
        expect(bloco.find('a[href="mailto:comercial@crm.test"]').exists()).toBe(true);
        expect(bloco.find('a[href="https://wa.me/5511999998888"]').exists()).toBe(true);
    });

    it('plano ativo oferece cancelar a renovação; cancelada, oferece retomar', async () => {
        const ativa = montar({ assinatura: assinatura({ status: 'ativa', ciclo: 'mensal', assentos: 4, vigente_ate: '2026-11-24', dias_restantes: 31 }) });
        const cancelar = ativa.findAll('button').find((b) => b.text() === 'Cancelar renovação');
        expect(cancelar).toBeDefined();
        await cancelar.trigger('click');
        await flushPromises();
        expect(confirmar.mock.calls[0][0].tom).toBe('danger');
        expect(post).toHaveBeenCalledWith('/configuracoes.assinatura.cancelar-renovacao', {}, expect.any(Object));

        const cancelada = montar({ assinatura: assinatura({ status: 'ativa', ciclo: 'mensal', assentos: 4, vigente_ate: '2026-11-24', renovacao_cancelada: true }) });
        expect(cancelada.findAll('button').some((b) => b.text() === 'Retomar renovação')).toBe(true);
        expect(cancelada.text()).toContain('não será renovado');
    });

    it('lista o histórico de faturas com a situação de cada uma', () => {
        const w = montar({
            faturas: [
                { id: 8, plano_nome: 'Profissional', ciclo: 'mensal', usuarios: 4, credito: 0, valor: 356, vence_em: '2026-10-20', paga_em: null, status: 'pendente', atrasada: true },
                { id: 6, plano_nome: 'Inicial', ciclo: 'mensal', usuarios: 3, credito: 0, valor: 147, vence_em: '2026-09-20', paga_em: '2026-09-19', status: 'paga', atrasada: false },
                { id: 5, plano_nome: 'Inicial', ciclo: 'mensal', usuarios: 3, credito: 0, valor: 147, vence_em: '2026-09-18', paga_em: null, status: 'cancelada', atrasada: false },
            ],
        });
        const linhas = w.findAll('[data-faturas] tbody tr');
        expect(linhas).toHaveLength(3);
        expect(linhas[0].text()).toContain('Atrasada');
        expect(linhas[1].text()).toContain('Paga em 19/09/2026');
        expect(linhas[2].text()).toContain('Cancelada');
    });
});
