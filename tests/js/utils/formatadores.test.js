import { describe, expect, it } from 'vitest';
import {
    diasDesde,
    formatBRL,
    formatarData,
    formatarDataHora,
    formatarRelativo,
    formatarTamanho,
    iniciais,
    parseDataLocal,
} from '@/utils/formatadores';

const semNbsp = (s) => s.replace(/ /g, ' ');

describe('formatBRL', () => {
    it('formata em real brasileiro', () => {
        expect(semNbsp(formatBRL(1500))).toBe('R$ 1.500,00');
        expect(semNbsp(formatBRL('1500.5'))).toBe('R$ 1.500,50');
    });

    it('trata vazio como zero por padrão e aceita um texto de vazio', () => {
        expect(semNbsp(formatBRL(null))).toBe('R$ 0,00');
        expect(formatBRL(null, '—')).toBe('—');
        expect(semNbsp(formatBRL(0, '—'))).toBe('R$ 0,00');
    });
});

describe('datas', () => {
    it('parseDataLocal lê YYYY-MM-DD no fuso local, sem recuar um dia', () => {
        const d = parseDataLocal('2026-10-17');
        expect([d.getFullYear(), d.getMonth(), d.getDate()]).toEqual([2026, 9, 17]);
        expect(parseDataLocal(null)).toBeNull();
    });

    it('formatarData aceita data-só e timestamp ISO', () => {
        expect(formatarData('2026-10-17')).toBe('17/10/2026');
        expect(formatarData('2026-10-17T14:05:00-03:00')).toMatch(/^\d{2}\/\d{2}\/2026$/);
        expect(formatarData(null)).toBeNull();
        expect(formatarData('abc')).toBeNull();
    });

    it('formatarDataHora inclui hora e minuto', () => {
        expect(formatarDataHora('2026-10-17T14:05:00')).toBe('17/10/2026 14:05');
        expect(formatarDataHora(null)).toBe('');
    });
});

describe('formatarRelativo e diasDesde', () => {
    const agora = new Date('2026-10-17T12:00:00').getTime();
    const dias = (n) => new Date(agora - n * 86400000).toISOString();

    it('conta dias a partir de um "agora" injetável', () => {
        expect(diasDesde(dias(3), agora)).toBe(3);
        expect(diasDesde(null, agora)).toBeNull();
    });

    it('fala em hoje, ontem, dias, meses e anos', () => {
        expect(formatarRelativo(dias(0), agora)).toBe('hoje');
        expect(formatarRelativo(dias(1), agora)).toBe('ontem');
        expect(formatarRelativo(dias(12), agora)).toBe('há 12 dias');
        expect(formatarRelativo(dias(70), agora)).toBe('há 2 meses');
        expect(formatarRelativo(dias(800), agora)).toBe('há 2 anos');
        expect(formatarRelativo(dias(400), agora)).toBe('há 1 ano');
        expect(formatarRelativo(null, agora)).toBeNull();
    });
});

describe('formatarTamanho', () => {
    it('escolhe a unidade', () => {
        expect(formatarTamanho(0)).toBe('—');
        expect(formatarTamanho(512)).toBe('512 B');
        expect(formatarTamanho(2048)).toBe('2,0 KB');
        expect(formatarTamanho(3 * 1048576)).toBe('3,0 MB');
    });
});

describe('iniciais', () => {
    it('usa a primeira letra do nome e do sobrenome', () => {
        expect(iniciais('Ana Souza')).toBe('AS');
        expect(iniciais('  diego   lima  santos ')).toBe('DL');
        expect(iniciais('Marina')).toBe('M');
        expect(iniciais('')).toBe('?');
        expect(iniciais(null)).toBe('?');
    });
});
