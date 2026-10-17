import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

const get = vi.fn().mockResolvedValue({ data: [{ id: 1, descricao: 'Preço' }, { id: 2, descricao: 'Concorrente' }] });
vi.mock('axios', () => ({ default: { get } }));
globalThis.route = (nome) => `/${nome}`;

const { default: ModalMotivoPerda } = await import('@/Components/ModalMotivoPerda.vue');

describe('ModalMotivoPerda', () => {
    it('carrega os motivos ao abrir, exige um e emite o payload', async () => {
        const w = mount(ModalMotivoPerda, { props: { aberto: true, titulo: 'Perder “Ana”' }, attachTo: document.body, global: { mocks: { route: globalThis.route } } });
        await new Promise((r) => setTimeout(r, 0));
        await nextTick();
        expect(get).toHaveBeenCalledWith('/api.motivos-perda.index', expect.anything());
        expect(w.text()).toContain('Perder “Ana”');
        const opcoes = w.findAll('option');
        expect(opcoes.map((o) => o.text())).toEqual(['Preço', 'Concorrente']);
        await w.find('textarea').setValue('Achou caro');
        await w.findAll('button').find((b) => b.text().includes('Registrar perda')).trigger('click');
        expect(w.emitted('confirmar')[0]).toEqual([{ motivo_perda_id: 1, observacao: 'Achou caro' }]);
        w.unmount();
    });
});
