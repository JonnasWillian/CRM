import { beforeEach, describe, expect, it, vi } from 'vitest';
import { usePerda } from '@/Composables/usePerda';

describe('usePerda', () => {
    beforeEach(() => usePerda().cancelar());

    it('pedirPerda abre o pedido, envia ao confirmar e resolve true', async () => {
        const { pendente, pedirPerda, confirmar } = usePerda();
        const enviar = vi.fn().mockResolvedValue();
        const promessa = pedirPerda({ titulo: 'Perder “Ana”', enviar });
        expect(pendente.value.titulo).toBe('Perder “Ana”');
        await confirmar({ motivo_perda_id: 3, observacao: null });
        expect(enviar).toHaveBeenCalledWith({ motivo_perda_id: 3, observacao: null });
        await expect(promessa).resolves.toBe(true);
        expect(pendente.value).toBeNull();
    });

    it('erro do backend mantém o modal aberto com a mensagem; cancelar resolve false', async () => {
        const { pendente, pedirPerda, confirmar, cancelar } = usePerda();
        const enviar = vi.fn().mockRejectedValue({ response: { status: 422, data: { errors: { motivo_perda_id: ['Motivo inválido.'] } } } });
        const promessa = pedirPerda({ titulo: 'X', enviar });
        await confirmar({ motivo_perda_id: 99, observacao: null });
        expect(pendente.value).not.toBeNull();
        expect(pendente.value.erro).toBe('Motivo inválido.');
        expect(pendente.value.salvando).toBe(false);
        cancelar();
        await expect(promessa).resolves.toBe(false);
    });
});
