import { beforeEach, describe, expect, it } from 'vitest';
import { useConfirm } from '@/Composables/useConfirm';

describe('useConfirm', () => {
    beforeEach(() => {
        const { pendente, responder } = useConfirm();
        if (pendente.value) responder(false);
    });

    it('confirmar expõe o pedido e resolve true quando respondido', async () => {
        const { pendente, confirmar, responder } = useConfirm();
        const promessa = confirmar({ titulo: 'Excluir o lead Ana?', mensagem: 'Vai para a lixeira.', rotuloConfirmar: 'Excluir lead', tom: 'danger' });
        expect(pendente.value).toMatchObject({ titulo: 'Excluir o lead Ana?', rotuloConfirmar: 'Excluir lead', rotuloCancelar: 'Cancelar', tom: 'danger' });
        responder(true);
        await expect(promessa).resolves.toBe(true);
        expect(pendente.value).toBeNull();
    });

    it('cancelar resolve false', async () => {
        const { confirmar, responder } = useConfirm();
        const promessa = confirmar({ titulo: 'Sair sem salvar?' });
        responder(false);
        await expect(promessa).resolves.toBe(false);
    });

    it('um novo pedido cancela o anterior', async () => {
        const { confirmar, responder, pendente } = useConfirm();
        const primeiro = confirmar({ titulo: 'A' });
        const segundo = confirmar({ titulo: 'B' });
        await expect(primeiro).resolves.toBe(false);
        expect(pendente.value.titulo).toBe('B');
        responder(true);
        await expect(segundo).resolves.toBe(true);
    });

    it('padrões: tom primary e rótulos', () => {
        const { confirmar, pendente, responder } = useConfirm();
        confirmar({ titulo: 'Tornar padrão?' });
        expect(pendente.value).toMatchObject({ tom: 'primary', rotuloConfirmar: 'Confirmar', rotuloCancelar: 'Cancelar', requerDigitacao: null });
        responder(false);
    });
});
