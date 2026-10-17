import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

/**
 * Os tokens de cor do theme.css cumprem WCAG AA: 4.5:1 para texto, 3:1 para
 * anel de foco e marcas não textuais. Calculado da fonte, não conferido no
 * olho — a cor de rótulo pequena (fg-2) é exatamente a que reprova quando
 * alguém "clareia um pouco" sem medir.
 */
const css = readFileSync(resolve(process.cwd(), 'resources/css/theme.css'), 'utf8');

function token(nome) {
    const m = css.match(new RegExp(`--${nome}:\\s*(#[0-9a-fA-F]{6})\\b`));
    if (!m) throw new Error(`token --${nome} não encontrado como hex literal no theme.css`);
    return m[1];
}

function luminancia(hex) {
    const canal = (c) => {
        const v = parseInt(c, 16) / 255;
        return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    };
    const r = canal(hex.slice(1, 3));
    const g = canal(hex.slice(3, 5));
    const b = canal(hex.slice(5, 7));
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

export function contraste(a, b) {
    const la = luminancia(a);
    const lb = luminancia(b);
    const [claro, escuro] = la > lb ? [la, lb] : [lb, la];
    return (claro + 0.05) / (escuro + 0.05);
}

const superficies = ['bg-0', 'bg-1', 'bg-2'];

describe('tokens de texto', () => {
    it.each(['fg-0', 'fg-1', 'fg-2'])('--%s tem ≥ 4.5:1 sobre as três superfícies', (fg) => {
        for (const bg of superficies) {
            expect(contraste(token(fg), token(bg)), `${fg} sobre ${bg}`).toBeGreaterThanOrEqual(4.5);
        }
    });

    it('texto primário sobre o botão primário tem ≥ 4.5:1', () => {
        expect(contraste(token('primary-fg'), token('primary'))).toBeGreaterThanOrEqual(4.5);
        expect(contraste(token('primary-fg'), token('primary-hover'))).toBeGreaterThanOrEqual(4.5);
    });

    it('a cor primária funciona como texto sobre branco e sobre o fundo sutil', () => {
        expect(contraste(token('primary'), token('bg-1'))).toBeGreaterThanOrEqual(4.5);
        expect(contraste(token('primary'), token('primary-subtle'))).toBeGreaterThanOrEqual(4.5);
    });
});

describe('tokens de status e categóricos como texto', () => {
    it.each(['ok', 'warn', 'danger', 'info'])('--%s tem ≥ 4.5:1 sobre branco e sobre o próprio fundo sutil', (tom) => {
        expect(contraste(token(tom), token('bg-1')), `${tom} sobre bg-1`).toBeGreaterThanOrEqual(4.5);
        expect(contraste(token(tom), token(`${tom}-subtle`)), `${tom} sobre ${tom}-subtle`).toBeGreaterThanOrEqual(4.5);
    });

    it.each(['cat-1', 'cat-2', 'cat-3', 'cat-4', 'cat-neutral'])('--%s tem ≥ 4.5:1 sobre branco', (cat) => {
        expect(contraste(token(cat), token('bg-1'))).toBeGreaterThanOrEqual(4.5);
    });
});

describe('marcas não textuais', () => {
    it('anel de foco tem ≥ 3:1 sobre branco e sobre o fundo da página', () => {
        expect(contraste(token('focus-ring'), token('bg-1'))).toBeGreaterThanOrEqual(3);
        expect(contraste(token('focus-ring'), token('bg-0'))).toBeGreaterThanOrEqual(3);
    });

    it('acento de alerta (barras e ícones) tem ≥ 3:1 sobre branco', () => {
        expect(contraste(token('warn-accent'), token('bg-1'))).toBeGreaterThanOrEqual(3);
    });

    it('bordas são distinguíveis da superfície (≥ 1.2:1) sem gritar (≤ 3:1)', () => {
        const r = contraste(token('line'), token('bg-1'));
        expect(r).toBeGreaterThanOrEqual(1.2);
        expect(r).toBeLessThanOrEqual(3);
    });
});

describe('tema claro', () => {
    it('as superfícies são claras (luminância alta) e o texto principal é escuro', () => {
        for (const bg of superficies) expect(luminancia(token(bg))).toBeGreaterThan(0.8);
        expect(luminancia(token('fg-0'))).toBeLessThan(0.05);
    });
});
