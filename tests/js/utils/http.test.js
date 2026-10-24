import { beforeEach, describe, expect, it, vi } from 'vitest';
import { tratarErroHttp } from '@/utils/http';

const toast = { erro: vi.fn(), aviso: vi.fn() };
const aoDeslogar = vi.fn();
const resposta = (status, data = {}, config = {}) => ({ response: { status, data }, config });

describe('tratarErroHttp', () => {
    beforeEach(() => {
        toast.erro.mockClear();
        aoDeslogar.mockClear();
    });

    it('401 manda para o login', () => {
        tratarErroHttp(resposta(401), { toast, aoDeslogar });
        expect(aoDeslogar).toHaveBeenCalledOnce();
        expect(toast.erro).not.toHaveBeenCalled();
    });

    it('403, 404 e 429 viram toast orientativo', () => {
        tratarErroHttp(resposta(403), { toast, aoDeslogar });
        tratarErroHttp(resposta(404), { toast, aoDeslogar });
        tratarErroHttp(resposta(429), { toast, aoDeslogar });
        const mensagens = toast.erro.mock.calls.map((c) => c[0]);
        expect(mensagens).toEqual([
            'Você não tem permissão para esta ação.',
            'Registro não encontrado. Pode ter sido removido por outra pessoa.',
            'Muitas tentativas. Aguarde um instante.',
        ]);
    });

    it('419 oferece recarregar', () => {
        tratarErroHttp(resposta(419), { toast, aoDeslogar });
        expect(toast.erro).toHaveBeenCalledWith('Sua sessão expirou.', expect.objectContaining({ acao: expect.objectContaining({ rotulo: 'Recarregar' }) }));
    });

    it('422 com campos fica em silêncio (o formulário mostra); sem campos, mostra a regra', () => {
        tratarErroHttp(resposta(422, { message: 'x', errors: { nome: ['Obrigatório'] } }), { toast, aoDeslogar });
        expect(toast.erro).not.toHaveBeenCalled();
        tratarErroHttp(resposta(422, { error: 'Este é o último estágio aberto do funil.' }), { toast, aoDeslogar });
        expect(toast.erro).toHaveBeenCalledWith('Este é o último estágio aberto do funil.');
    });

    it('5xx e falha de rede viram a mensagem genérica', () => {
        tratarErroHttp(resposta(500), { toast, aoDeslogar });
        tratarErroHttp(new Error('Network Error'), { toast, aoDeslogar });
        expect(toast.erro).toHaveBeenCalledTimes(2);
        expect(toast.erro.mock.calls[0][0]).toBe('Não foi possível concluir. Tente novamente.');
    });

    it('402 (assinatura bloqueada) avisa mesmo em chamada silenciosa e oferece ver os planos', () => {
        tratarErroHttp(resposta(402, { message: 'Seu período de teste terminou.' }, { silencioso: true }), { toast, aoDeslogar });
        expect(toast.erro).toHaveBeenCalledOnce();
        expect(toast.erro.mock.calls[0][0]).toBe('Seu período de teste terminou.');
        expect(toast.erro.mock.calls[0][1].acao.rotulo).toBe('Ver planos');
    });

    it('402 de acesso suspenso leva à página de acesso suspenso, sem toast', () => {
        const antes = window.location;
        delete window.location;
        window.location = { href: '/leads' };
        tratarErroHttp(resposta(402, { message: 'Suspenso.', assinatura: 'suspensa' }), { toast, aoDeslogar });
        expect(window.location.href).toBe('/assinatura/suspensa');
        expect(toast.erro).not.toHaveBeenCalled();
        window.location = antes;
    });

    it('config.silencioso desliga o tratamento', () => {
        tratarErroHttp(resposta(500, {}, { silencioso: true }), { toast, aoDeslogar });
        expect(toast.erro).not.toHaveBeenCalled();
    });
});
