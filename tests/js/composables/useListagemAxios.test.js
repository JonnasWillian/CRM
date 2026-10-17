import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

const get = vi.fn();
vi.mock('axios', () => ({ default: { get, isCancel: (e) => e?.name === 'CanceledError' } }));

const { useListagemAxios } = await import('@/Composables/useListagemAxios');

const paginado = (data, extra = {}) => ({ data: { data, meta: { current_page: 1, last_page: 1, per_page: 20, total: data.length, from: 1, to: data.length }, sort: { por: 'created_at', dir: 'desc' }, ...extra } });
const esperar = () => new Promise((r) => setTimeout(r, 0));

describe('useListagemAxios', () => {
    beforeEach(() => {
        get.mockReset();
        vi.useFakeTimers({ toFake: ['setTimeout'] });
    });
    afterEach(() => vi.useRealTimers());

    it('carrega ao iniciar, expõe linhas/meta/ordenação e marca a chamada como silenciosa', async () => {
        get.mockResolvedValue(paginado([{ id: 1 }, { id: 2 }]));
        const lista = useListagemAxios('/api/leads/9/anotacoes', { padrao: { per_page: 20 } });
        expect(lista.carregando.value).toBe(true);
        await vi.runAllTimersAsync();
        expect(get).toHaveBeenCalledWith('/api/leads/9/anotacoes', expect.objectContaining({ params: {}, silencioso: true }));
        expect(lista.linhas.value).toHaveLength(2);
        expect(lista.meta.value.total).toBe(2);
        expect(lista.ordenacao.value).toEqual({ por: 'created_at', dir: 'desc' });
        expect(lista.carregando.value).toBe(false);
    });

    it('aceita resposta em array simples (sem paginação)', async () => {
        get.mockResolvedValue({ data: [{ id: 1 }] });
        const lista = useListagemAxios('/api/status');
        await vi.runAllTimersAsync();
        expect(lista.linhas.value).toEqual([{ id: 1 }]);
        expect(lista.meta.value).toBeNull();
    });

    it('ordenar, paginar e buscar refazem a chamada com os parâmetros; a anterior é cancelada', async () => {
        get.mockResolvedValue(paginado([]));
        const lista = useListagemAxios('/api/x', { padrao: { per_page: 20 } });
        await vi.runAllTimersAsync();
        lista.ordenarPor('nome');
        await vi.runAllTimersAsync();
        expect(get.mock.calls.at(-1)[1].params).toEqual({ sort: 'nome', dir: 'asc' });
        lista.irParaPagina(3);
        await vi.runAllTimersAsync();
        expect(get.mock.calls.at(-1)[1].params).toEqual({ sort: 'nome', dir: 'asc', page: 3 });
        lista.busca.value = 'ana';
        await nextTick();
        vi.advanceTimersByTime(300);
        await vi.runAllTimersAsync();
        expect(get.mock.calls.at(-1)[1].params).toEqual({ sort: 'nome', dir: 'asc', busca: 'ana' });
        const sinais = get.mock.calls.map((c) => c[1].signal);
        expect(sinais[0].aborted).toBe(true);
        expect(sinais.at(-1).aborted).toBe(false);
    });

    it('erro de rede vira `erro` e recarregar() tenta de novo', async () => {
        get.mockRejectedValueOnce(new Error('Network Error')).mockResolvedValueOnce(paginado([{ id: 7 }]));
        const lista = useListagemAxios('/api/x');
        await vi.runAllTimersAsync();
        expect(lista.erro.value).toBe('Não foi possível concluir. Tente novamente.');
        expect(lista.linhas.value).toEqual([]);
        lista.recarregar();
        await vi.runAllTimersAsync();
        expect(lista.erro.value).toBeNull();
        expect(lista.linhas.value).toEqual([{ id: 7 }]);
    });
});
