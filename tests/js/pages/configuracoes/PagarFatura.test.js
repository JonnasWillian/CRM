import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const post = vi.fn();
const get = vi.fn();
vi.mock('axios', () => ({ default: { post: (...a) => post(...a), get: (...a) => get(...a) } }));

globalThis.route = (nome, id) => `/${nome}/${id}`;

const { default: PagarFatura } = await import('@/Components/assinatura/PagarFatura.vue');

const FATURA = { id: 7, valor: 356, plano_nome: 'Profissional' };
const PIX = { imagem: 'data:image/png;base64,iVBOR', copia_e_cola: '00020101pix', expira_em: '2027-11-03 23:59:59' };
const erro = (status, data) => Object.assign(new Error('http'), { response: { status, data } });

function montar(props = {}) {
    return mount(PagarFatura, { props: { aberto: true, fatura: FATURA, precisaDocumento: false, ...props }, global: { mocks: { route: globalThis.route } } });
}

describe('Pagar fatura pelo Asaas', () => {
    beforeEach(() => {
        post.mockReset();
        get.mockReset();
    });
    afterEach(() => vi.useRealTimers());

    it('na primeira vez pede o CPF ou CNPJ e depois mostra o QR Code, o copia e cola e o link de boleto ou cartão', async () => {
        post.mockResolvedValue({ data: { pix: PIX, link: 'https://www.asaas.com/i/pay_1' } });
        const w = montar({ precisaDocumento: true });
        expect(post).not.toHaveBeenCalled();

        await w.find('input').setValue('11.222.333/0001-81');
        await w.find('form').trigger('submit');
        await flushPromises();

        expect(post).toHaveBeenCalledWith('/configuracoes.assinatura.pagar/7', { documento: '11.222.333/0001-81' }, { silencioso: true });
        expect(w.find('[data-pix] img').attributes('src')).toBe(PIX.imagem);
        expect(w.find('#pix-copia-e-cola').element.value).toBe('00020101pix');
        expect(w.find('a[href="https://www.asaas.com/i/pay_1"]').text()).toContain('Pagar com boleto ou cartão');
    });

    it('com o documento já salvo, gera o Pix ao abrir', async () => {
        post.mockResolvedValue({ data: { pix: PIX, link: null } });
        const w = montar();
        await flushPromises();

        expect(post).toHaveBeenCalledWith('/configuracoes.assinatura.pagar/7', {}, { silencioso: true });
        expect(w.find('[data-pix]').exists()).toBe(true);
    });

    it('CPF ou CNPJ recusado volta para o campo com a mensagem', async () => {
        post.mockRejectedValue(erro(422, { message: 'x', errors: { documento: ['CPF ou CNPJ inválido. Confira os números.'] } }));
        const w = montar({ precisaDocumento: true });

        await w.find('input').setValue('11.222.333/0001-80');
        await w.find('form').trigger('submit');
        await flushPromises();

        expect(w.text()).toContain('CPF ou CNPJ inválido. Confira os números.');
        expect(w.find('input').exists()).toBe(true);
    });

    it('falha do Asaas mostra a mensagem do servidor e deixa tentar de novo', async () => {
        post.mockRejectedValueOnce(erro(503, { message: 'O sistema de pagamento não respondeu agora. Tente de novo em instantes.' }));
        post.mockResolvedValueOnce({ data: { pix: PIX, link: null } });
        const w = montar();
        await flushPromises();

        expect(w.text()).toContain('O sistema de pagamento não respondeu agora.');
        await w.findAll('button').find((b) => b.text() === 'Tentar de novo').trigger('click');
        await flushPromises();

        expect(post).toHaveBeenCalledTimes(2);
        expect(w.find('[data-pix]').exists()).toBe(true);
    });

    it('fechar enquanto o Pix é gerado não deixa consulta rodando', async () => {
        vi.useFakeTimers();
        let responder;
        post.mockReturnValue(new Promise((r) => (responder = r)));
        const w = montar();

        await w.setProps({ aberto: false });
        responder({ data: { pix: PIX, link: null } });
        await flushPromises();
        await vi.advanceTimersByTimeAsync(15000);

        expect(get).not.toHaveBeenCalled();
    });

    it('consulta a fatura a cada 5 segundos e, quando o pagamento cai, mostra a confirmação', async () => {
        vi.useFakeTimers();
        post.mockResolvedValue({ data: { pix: PIX, link: null } });
        get.mockResolvedValueOnce({ data: { status: 'pendente' } }).mockResolvedValueOnce({ data: { status: 'paga' } });
        const w = montar();
        await flushPromises();

        await vi.advanceTimersByTimeAsync(5000);
        expect(w.find('[data-pago]').exists()).toBe(false);
        await vi.advanceTimersByTimeAsync(5000);

        expect(get).toHaveBeenCalledWith('/configuracoes.assinatura.fatura/7', { silencioso: true });
        expect(w.find('[data-pix]').exists()).toBe(false);
        expect(w.find('[data-pago]').text()).toContain('Pagamento confirmado');
        expect(w.find('[data-pago]').text()).toContain('O plano Profissional já está ativo.');
        await vi.advanceTimersByTimeAsync(10000);
        expect(get).toHaveBeenCalledTimes(2); // parou de consultar

        expect(w.emitted('paga')).toBeUndefined(); // a página só recarrega quando a pessoa conclui
        await w.findAll('button').find((b) => b.text() === 'Concluir').trigger('click');
        expect(w.emitted('paga')).toHaveLength(1);
    });

    it('fechar na tela de confirmação também conta como pago', async () => {
        vi.useFakeTimers();
        post.mockResolvedValue({ data: { pix: PIX, link: null } });
        get.mockResolvedValue({ data: { status: 'paga' } });
        const w = montar();
        await flushPromises();
        await vi.advanceTimersByTimeAsync(5000);

        w.findComponent({ name: 'AppModal' }).vm.$emit('fechar');

        expect(w.emitted('paga')).toHaveLength(1);
        expect(w.emitted('fechar')).toBeUndefined();
    });

    it('mostra a hora em que conferiu o pagamento pela última vez', async () => {
        vi.useFakeTimers();
        const conferido = '2026-11-01T21:03:15-03:00';
        post.mockResolvedValue({ data: { pix: PIX, link: null } });
        get.mockResolvedValue({ data: { status: 'pendente', conferido_em: conferido } });
        const w = montar();
        await flushPromises();
        expect(w.find('[data-pix]').text()).not.toContain('Última conferência');

        await vi.advanceTimersByTimeAsync(5000);

        const hora = new Date(conferido).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        expect(w.find('[data-pix]').text()).toContain(`Última conferência às ${hora}.`);
    });

    it('depois de 1 minuto sem pagamento, explica o que conferir', async () => {
        vi.useFakeTimers();
        post.mockResolvedValue({ data: { pix: PIX, link: null } });
        get.mockResolvedValue({ data: { status: 'pendente' } });
        const w = montar();
        await flushPromises();

        await vi.advanceTimersByTimeAsync(55000);
        expect(w.text()).not.toContain('Pagou e ainda não confirmou?');
        await vi.advanceTimersByTimeAsync(5000);

        expect(w.text()).toContain('Pagou e ainda não confirmou?');
        expect(w.text()).toContain('Pix enviado para outra chave não paga a fatura');
    });
});
