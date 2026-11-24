import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';

let rotaAtual = 'kanban';
const post = vi.fn();

vi.mock('axios', () => ({ default: { post: (...a) => post(...a) } }));
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => ({ props: { app: { nome: 'UserFlow' }, auth: { user: { name: 'Ana Souza', email: 'ana@acme.test' } } } }),
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

globalThis.route = (nome) => (nome === undefined ? { current: (n) => n === rotaAtual || (n.endsWith('*') && rotaAtual.startsWith(n.slice(0, -1))) } : `/${nome}`);

async function montar() {
    const { default: AssistenteDeAjuda } = await import('@/Components/suporte/AssistenteDeAjuda.vue');
    const w = mount(AssistenteDeAjuda, { attachTo: document.body, global: { mocks: { route: globalThis.route } } });
    await w.find('[data-suporte-abrir]').trigger('click');
    return w;
}
const botao = (w, texto) => w.findAll('button').find((b) => b.text().trim().startsWith(texto));
const clicar = (w, texto) => botao(w, texto).trigger('click');
const passo = (w) => w.find('[data-passo]').attributes('data-passo');

/** Assistente de ajuda e feedback: confirma os dados, faz a triagem e só então fala com o suporte. */
describe('AssistenteDeAjuda', () => {
    beforeEach(() => {
        vi.resetModules();
        post.mockReset();
        rotaAtual = 'kanban';
        document.body.innerHTML = '';
    });

    it('abre pelo botão e pede para confirmar nome e e-mail da conta', async () => {
        const w = await montar();

        expect(passo(w)).toBe('dados');
        expect(w.text()).toContain('Ana Souza');
        expect(w.text()).toContain('ana@acme.test');
        expect(w.find('[role="dialog"]').exists()).toBe(true);

        await clicar(w, 'Sim, pode usar');
        expect(passo(w)).toBe('assunto');
        w.unmount();
    });

    it('outro e-mail de contato precisa ser válido e vai no envio', async () => {
        post.mockResolvedValue({ data: { protocolo: 31, suporte_acionado: true, email_contato: 'fin@acme.test' } });
        const w = await montar();

        await clicar(w, 'Usar outro e-mail');
        await w.find('input[type="email"]').setValue('nao-e-email');
        await clicar(w, 'Continuar');
        expect(passo(w)).toBe('dados');
        expect(w.text()).toContain('Informe um e-mail válido.');

        await w.find('input[type="email"]').setValue('fin@acme.test');
        await clicar(w, 'Continuar');
        await clicar(w, 'Cobrança e assinatura'); // vai direto ao relato: a área é a assinatura
        expect(passo(w)).toBe('relato');
        await w.find('textarea').setValue('Paguei o Pix e o plano não ativou.');
        await clicar(w, 'Enviar ao suporte');
        await flushPromises();

        expect(post).toHaveBeenCalledWith('/api.suporte.store', expect.objectContaining({ tipo: 'cobranca', area: 'assinatura', email_contato: 'fin@acme.test', acionar_suporte: true }), { silencioso: true });
        expect(w.text()).toContain('fin@acme.test');
        w.unmount();
    });

    it('problema: área já vem da tela atual, pede impacto e relato, envia e mostra o protocolo', async () => {
        post.mockResolvedValue({ data: { protocolo: 42, suporte_acionado: true, email_contato: 'ana@acme.test' } });
        const w = await montar();
        await clicar(w, 'Sim, pode usar');
        await clicar(w, 'Algo não funciona');

        expect(passo(w)).toBe('area');
        expect(botao(w, 'Pipeline').attributes('aria-pressed')).toBe('true');
        await clicar(w, 'Continuar');

        await clicar(w, 'Enviar ao suporte');
        expect(post).not.toHaveBeenCalled();
        expect(w.text()).toContain('Conte um pouco mais: pelo menos 10 caracteres.');
        expect(w.text()).toContain('Diga o quanto isso atrapalha.');

        await w.find('textarea').setValue('O cartão não muda de estágio.');
        await clicar(w, 'Atrapalha, mas consigo trabalhar');
        await clicar(w, 'Enviar ao suporte');
        await flushPromises();

        expect(post).toHaveBeenCalledWith('/api.suporte.store', {
            tipo: 'problema', area: 'pipeline', impacto: 'atrapalha', mensagem: 'O cartão não muda de estágio.',
            nota: null, email_contato: null, acionar_suporte: true, pagina: window.location.pathname,
        }, { silencioso: true });
        expect(passo(w)).toBe('fim');
        expect(w.text()).toContain('#42');
        expect(w.text()).toContain('A resposta pode levar até 5 dias úteis.');
        expect(w.text()).toContain('ana@acme.test');
        w.unmount();
    });

    it('dúvida: mostra dicas da área; se resolveu, termina sem enviar nada', async () => {
        const w = await montar();
        await clicar(w, 'Sim, pode usar');
        await clicar(w, 'Tenho uma dúvida');
        await clicar(w, 'Continuar');

        expect(passo(w)).toBe('dicas');
        expect(w.findAll('[data-dica]').length).toBeGreaterThan(0);

        await clicar(w, 'Resolveu');
        expect(passo(w)).toBe('fim');
        expect(post).not.toHaveBeenCalled();
        w.unmount();
    });

    it('dúvida que as dicas não resolveram segue para o suporte', async () => {
        const w = await montar();
        await clicar(w, 'Sim, pode usar');
        await clicar(w, 'Tenho uma dúvida');
        await clicar(w, 'Continuar');

        await clicar(w, 'Ainda preciso de ajuda');
        expect(passo(w)).toBe('relato');
        expect(botao(w, 'Enviar ao suporte')).toBeDefined();
        w.unmount();
    });

    it('sugestão: nota opcional e resposta do suporte só se a pessoa pedir', async () => {
        post.mockResolvedValue({ data: { protocolo: 7, suporte_acionado: false, email_contato: 'ana@acme.test' } });
        const w = await montar();
        await clicar(w, 'Sim, pode usar');
        await clicar(w, 'Tenho uma sugestão');
        await clicar(w, 'Continuar');
        await w.find('textarea').setValue('Queria filtrar o pipeline por origem.');
        await w.find('[data-nota="9"]').trigger('click');
        await clicar(w, 'Enviar feedback');
        await flushPromises();

        expect(post.mock.calls[0][1]).toMatchObject({ tipo: 'sugestao', nota: 9, acionar_suporte: false, impacto: null });
        expect(w.text()).toContain('Obrigado');
        expect(w.text()).not.toContain('#7');
        w.unmount();
    });

    it('falha no envio mostra a mensagem do servidor e mantém o texto', async () => {
        post.mockRejectedValue({ response: { status: 429, data: { message: 'Você já enviou várias mensagens hoje.' } } });
        const w = await montar();
        await clicar(w, 'Sim, pode usar');
        await clicar(w, 'Cobrança e assinatura');
        await w.find('textarea').setValue('Paguei o Pix e o plano não ativou.');
        await clicar(w, 'Enviar ao suporte');
        await flushPromises();

        expect(passo(w)).toBe('relato');
        expect(w.text()).toContain('Você já enviou várias mensagens hoje.');
        expect(w.find('textarea').element.value).toBe('Paguei o Pix e o plano não ativou.');
        w.unmount();
    });

    it('Voltar retorna ao passo anterior e Esc fecha sem perder o que foi escrito', async () => {
        const w = await montar();
        await clicar(w, 'Sim, pode usar');
        await clicar(w, 'Cobrança e assinatura');
        await w.find('textarea').setValue('Rascunho do meu relato.');

        await w.find('button[aria-label="Voltar"]').trigger('click');
        expect(passo(w)).toBe('assunto');
        await clicar(w, 'Cobrança e assinatura');

        await w.find('[role="dialog"]').trigger('keydown', { key: 'Escape' });
        expect(w.find('[role="dialog"]').exists()).toBe(false);
        await w.find('[data-suporte-abrir]').trigger('click');
        expect(w.find('textarea').element.value).toBe('Rascunho do meu relato.');
        w.unmount();
    });
});
