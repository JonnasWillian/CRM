import { describe, expect, it } from 'vitest';
import { fimDoContrato, somarMeses } from '@/utils/recorrencia';

describe('recorrencia', () => {
    it('somarMeses não transborda o fim do mês (31/01 + 1 = 28/02)', () => {
        expect(somarMeses('2027-01-31', 1)).toBe('2027-02-28');
        expect(somarMeses('2026-08-15', 12)).toBe('2027-08-15');
        expect(somarMeses('2024-01-31', 1)).toBe('2024-02-29');
    });

    it('fim do contrato é a véspera do aniversário depois de N meses (mesma regra do servidor)', () => {
        expect(fimDoContrato('2026-08-15', 12)).toBe('2027-08-14');
        expect(fimDoContrato('2027-01-31', 3)).toBe('2027-04-29');
        expect(fimDoContrato('2026-08-15', 0)).toBeNull();
        expect(fimDoContrato(null, 3)).toBeNull();
    });
});
