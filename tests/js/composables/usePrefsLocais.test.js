import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { usePrefsLocais } from '@/Composables/usePrefsLocais';

describe('usePrefsLocais', () => {
    beforeEach(() => localStorage.clear());

    it('começa pelo padrão e grava com prefixo ui.', async () => {
        const prefs = usePrefsLocais('tabela.leads', { ocultas: [] });
        expect(prefs.value).toEqual({ ocultas: [] });
        prefs.value.ocultas.push('telefone');
        await nextTick();
        expect(JSON.parse(localStorage.getItem('ui.tabela.leads'))).toEqual({ ocultas: ['telefone'] });
    });

    it('lê o que já estava guardado', () => {
        localStorage.setItem('ui.sidebar.colapsada', 'true');
        expect(usePrefsLocais('sidebar.colapsada', false).value).toBe(true);
    });

    it('tolera storage indisponível', async () => {
        const getItem = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => { throw new Error('bloqueado'); });
        const setItem = vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => { throw new Error('bloqueado'); });
        const prefs = usePrefsLocais('x', 'padrão');
        expect(prefs.value).toBe('padrão');
        prefs.value = 'novo';
        await nextTick();
        expect(prefs.value).toBe('novo');
        getItem.mockRestore();
        setItem.mockRestore();
    });
});
