import { beforeEach, describe, expect, it, vi } from 'vitest';

/** Tema claro/escuro/sistema: guardado no navegador e aplicado em <html data-theme>. */
describe('useTema', () => {
    let escuroNoSistema;

    beforeEach(() => {
        vi.resetModules();
        localStorage.clear();
        document.documentElement.removeAttribute('data-theme');
        escuroNoSistema = false;
        window.matchMedia = vi.fn().mockImplementation(() => ({ matches: escuroNoSistema, addEventListener: vi.fn(), removeEventListener: vi.fn() }));
    });

    it('padrão é o tema do sistema; claro quando o sistema é claro', async () => {
        const { useTema } = await import('@/Composables/useTema');
        const { preferencia, aplicado } = useTema();
        expect(preferencia.value).toBe('sistema');
        expect(aplicado.value).toBe('claro');
        expect(document.documentElement.dataset.theme).toBe('claro');
    });

    it('segue o sistema escuro', async () => {
        escuroNoSistema = true;
        const { useTema } = await import('@/Composables/useTema');
        expect(useTema().aplicado.value).toBe('escuro');
    });

    it('escolher escuro aplica no <html> e persiste', async () => {
        const { useTema } = await import('@/Composables/useTema');
        const tema = useTema();
        tema.escolher('escuro');
        expect(document.documentElement.dataset.theme).toBe('escuro');
        expect(localStorage.getItem('ui.tema')).toBe('escuro');
        tema.escolher('claro');
        expect(document.documentElement.dataset.theme).toBe('claro');
    });

    it('valor inválido no storage cai no sistema', async () => {
        localStorage.setItem('ui.tema', 'roxo');
        const { useTema } = await import('@/Composables/useTema');
        expect(useTema().preferencia.value).toBe('sistema');
    });
});
