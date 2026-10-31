import { nextTick, ref } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useContador } from '@/Composables/useContador';

/**
 * O número dos KPIs: não anima na primeira carga, conta até o novo valor
 * quando ele muda com a tela aberta, e troca direto para quem pediu menos
 * movimento.
 */
describe('useContador', () => {
    const matchMediaOriginal = window.matchMedia;

    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['requestAnimationFrame', 'cancelAnimationFrame', 'performance'] });
        window.matchMedia = () => ({ matches: false });
    });

    afterEach(() => {
        vi.useRealTimers();
        window.matchMedia = matchMediaOriginal;
    });

    it('mostra o valor da primeira carga sem animar', () => {
        const valor = ref(1234);
        expect(useContador(() => valor.value).value).toBe(1234);
    });

    it('quando o valor muda, conta até ele e termina exatamente no novo valor', async () => {
        const valor = ref(0);
        const exibido = useContador(() => valor.value, 400);

        valor.value = 100;
        await nextTick();
        expect(exibido.value).toBe(0);

        vi.advanceTimersByTime(200);
        expect(exibido.value).toBeGreaterThan(0);
        expect(exibido.value).toBeLessThan(100);

        vi.advanceTimersByTime(300);
        expect(exibido.value).toBe(100);
    });

    it('para quem pediu menos movimento, troca direto', async () => {
        window.matchMedia = (consulta) => ({ matches: consulta === '(prefers-reduced-motion: reduce)' });
        const valor = ref(10);
        const exibido = useContador(() => valor.value);

        valor.value = 90;
        await nextTick();

        expect(exibido.value).toBe(90);
    });

    it('mudança no meio da animação continua do número na tela, sem pular', async () => {
        const valor = ref(0);
        const exibido = useContador(() => valor.value, 400);

        valor.value = 100;
        await nextTick();
        vi.advanceTimersByTime(200);
        const meio = exibido.value;

        valor.value = 50;
        await nextTick();
        vi.advanceTimersByTime(16);
        expect(Math.abs(exibido.value - meio)).toBeLessThan(15);

        vi.advanceTimersByTime(500);
        expect(exibido.value).toBe(50);
    });

    it('valor ausente conta como zero', async () => {
        const valor = ref(null);
        const exibido = useContador(() => valor.value);
        expect(exibido.value).toBe(0);

        window.matchMedia = () => ({ matches: true });
        valor.value = 5;
        await nextTick();
        valor.value = null;
        await nextTick();
        expect(exibido.value).toBe(0);
    });
});
