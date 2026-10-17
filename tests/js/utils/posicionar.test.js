import { describe, expect, it } from 'vitest';
import { posicionar } from '@/utils/posicionar';

const viewport = { width: 1000, height: 600 };
const gatilho = { top: 100, left: 100, width: 40, height: 20, right: 140, bottom: 120 };
const painel = { width: 200, height: 80 };

describe('posicionar', () => {
    it('abaixo do gatilho, alinhado ao início, com margem', () => {
        const p = posicionar(gatilho, painel, { lado: 'bottom', alinhar: 'start', margem: 6, viewport });
        expect(p).toEqual({ top: 126, left: 100, lado: 'bottom' });
    });

    it('alinha ao fim quando pedido', () => {
        const g = { ...gatilho, left: 360, right: 400 };
        const p = posicionar(g, painel, { lado: 'bottom', alinhar: 'end', margem: 6, viewport });
        expect(p.left).toBe(400 - 200);
    });

    it('nunca sai da tela: alinhar ao fim perto da borda esquerda fica na margem', () => {
        const p = posicionar(gatilho, painel, { lado: 'bottom', alinhar: 'end', margem: 6, viewport });
        expect(p.left).toBe(6);
    });

    it('vira para cima quando não cabe embaixo', () => {
        const baixo = { ...gatilho, top: 560, bottom: 580 };
        const p = posicionar(baixo, painel, { lado: 'bottom', alinhar: 'start', margem: 6, viewport });
        expect(p.lado).toBe('top');
        expect(p.top).toBe(560 - 6 - 80);
    });

    it('centraliza em top/bottom com alinhar=center e não sai da tela', () => {
        const borda = { ...gatilho, left: 980, right: 1000 };
        const p = posicionar(borda, painel, { lado: 'top', alinhar: 'center', margem: 4, viewport });
        expect(p.lado).toBe('top');
        expect(p.left).toBe(1000 - 200 - 4);
    });

    it('lados left/right centralizam verticalmente', () => {
        const p = posicionar(gatilho, painel, { lado: 'right', margem: 8, viewport });
        expect(p).toEqual({ top: 110 - 40, left: 148, lado: 'right' });
    });
});
