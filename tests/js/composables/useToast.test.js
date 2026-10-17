import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useToast } from '@/Composables/useToast';

describe('useToast', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        useToast().limpar();
    });
    afterEach(() => vi.useRealTimers());

    it('sucesso aparece e some sozinho depois de 4 s', () => {
        const { toasts, sucesso } = useToast();
        sucesso('Lead cadastrado.');
        expect(toasts.value).toHaveLength(1);
        expect(toasts.value[0]).toMatchObject({ tom: 'sucesso', mensagem: 'Lead cadastrado.' });
        vi.advanceTimersByTime(3999);
        expect(toasts.value).toHaveLength(1);
        vi.advanceTimersByTime(1);
        expect(toasts.value).toHaveLength(0);
    });

    it('erro fica até ser fechado', () => {
        const { toasts, erro, fechar } = useToast();
        const id = erro('Não foi possível concluir.');
        vi.advanceTimersByTime(60000);
        expect(toasts.value).toHaveLength(1);
        fechar(id);
        expect(toasts.value).toHaveLength(0);
    });

    it('com ação dura 7 s e a ação fecha o toast ao ser acionada', () => {
        const { toasts, sucesso, acionar } = useToast();
        const onClick = vi.fn();
        const id = sucesso('Movido.', { acao: { rotulo: 'Desfazer', onClick } });
        vi.advanceTimersByTime(6999);
        expect(toasts.value).toHaveLength(1);
        acionar(id);
        expect(onClick).toHaveBeenCalledOnce();
        expect(toasts.value).toHaveLength(0);
    });

    it('mostra no máximo 3; o quarto espera uma vaga', () => {
        const { toasts, info, fechar } = useToast();
        const ids = [1, 2, 3, 4].map((n) => info(`t${n}`));
        expect(toasts.value.map((t) => t.mensagem)).toEqual(['t1', 't2', 't3']);
        fechar(ids[0]);
        expect(toasts.value.map((t) => t.mensagem)).toEqual(['t2', 't3', 't4']);
    });

    it('pausar segura o relógio e retomar devolve a duração', () => {
        const { toasts, aviso, pausar, retomar } = useToast();
        const id = aviso('Atenção.');
        vi.advanceTimersByTime(3000);
        pausar(id);
        vi.advanceTimersByTime(60000);
        expect(toasts.value).toHaveLength(1);
        retomar(id);
        vi.advanceTimersByTime(6000);
        expect(toasts.value).toHaveLength(0);
    });
});
