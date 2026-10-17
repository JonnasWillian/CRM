import { beforeEach, describe, expect, it, vi } from 'vitest';

const listeners = {};
const router = {
    on: vi.fn((evento, fn) => {
        listeners[evento] = fn;
        return () => delete listeners[evento];
    }),
    visit: vi.fn(),
};
vi.mock('@inertiajs/vue3', () => ({ router }));

const { useUnsavedChanges } = await import('@/Composables/useUnsavedChanges');

const eventoBefore = (url = 'http://app.test/leads') => ({
    preventDefault: vi.fn(),
    detail: { visit: { url: new URL(url), method: 'get', data: {} } },
});

describe('useUnsavedChanges', () => {
    beforeEach(() => {
        router.visit.mockClear();
        for (const k of Object.keys(listeners)) delete listeners[k];
    });

    it('deixa navegar quando não há alterações', () => {
        useUnsavedChanges(() => false, { confirmar: vi.fn() });
        const e = eventoBefore();
        listeners.before(e);
        expect(e.preventDefault).not.toHaveBeenCalled();
    });

    it('segura a navegação e a retoma quando o usuário confirma sair', async () => {
        const confirmar = vi.fn().mockResolvedValue(true);
        useUnsavedChanges(() => true, { confirmar });
        const e = eventoBefore('http://app.test/kanban');
        listeners.before(e);
        expect(e.preventDefault).toHaveBeenCalled();
        expect(confirmar).toHaveBeenCalledWith(expect.objectContaining({ titulo: 'Sair sem salvar?' }));
        await Promise.resolve();
        await Promise.resolve();
        expect(router.visit).toHaveBeenCalledWith('http://app.test/kanban', expect.objectContaining({ method: 'get' }));
    });

    it('não navega quando o usuário prefere continuar editando', async () => {
        const confirmar = vi.fn().mockResolvedValue(false);
        useUnsavedChanges(() => true, { confirmar });
        listeners.before(eventoBefore());
        await Promise.resolve();
        await Promise.resolve();
        expect(router.visit).not.toHaveBeenCalled();
    });

    it('liberar() deixa a próxima navegação passar (depois de salvar) e parar() remove os ouvintes', () => {
        const confirmar = vi.fn();
        const guarda = useUnsavedChanges(() => true, { confirmar });
        guarda.liberar();
        const e = eventoBefore();
        listeners.before(e);
        expect(e.preventDefault).not.toHaveBeenCalled();
        guarda.parar();
        expect(listeners.before).toBeUndefined();
    });

    it('avisa o navegador ao fechar a aba com alterações', () => {
        useUnsavedChanges(() => true, { confirmar: vi.fn() });
        const e = new Event('beforeunload', { cancelable: true });
        window.dispatchEvent(e);
        expect(e.defaultPrevented).toBe(true);
    });
});
