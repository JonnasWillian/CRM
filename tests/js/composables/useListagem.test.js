import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

const router = { get: vi.fn(), on: vi.fn(() => () => {}) };
vi.mock('@inertiajs/vue3', () => ({ router }));
globalThis.route = (nome) => `/${nome.replace('.', '/')}`;

const { useListagem } = await import('@/Composables/useListagem');

const opcoes = () => ({
    rota: 'leads.index',
    only: ['leads'],
    padrao: { per_page: 25, sort: 'created_at', dir: 'desc' },
    filtros: ['busca', 'estagios', 'situacao'],
});

describe('useListagem', () => {
    beforeEach(() => {
        router.get.mockClear();
        history.replaceState(null, '', '/leads');
        vi.useFakeTimers();
    });
    afterEach(() => vi.useRealTimers());

    it('lê o estado inicial da URL, inclusive arrays, com fallback no padrão', () => {
        history.replaceState(null, '', '/leads?page=3&sort=nome&dir=asc&estagios[]=1&estagios[]=2&situacao=ativos');
        const lista = useListagem(opcoes());
        expect(lista.estado.page).toBe(3);
        expect(lista.estado.sort).toBe('nome');
        expect(lista.estado.dir).toBe('asc');
        expect(lista.estado.per_page).toBe(25);
        expect(lista.estado.estagios).toEqual([1, 2]);
        expect(lista.estado.situacao).toBe('ativos');
        expect(lista.filtrosAtivos.value).toBe(2);
    });

    it('aplicar() chama router.get com a query sem os valores padrão e zera a página', () => {
        history.replaceState(null, '', '/leads?page=4');
        const lista = useListagem(opcoes());
        lista.aplicar({ situacao: 'ativos' });
        expect(router.get).toHaveBeenCalledOnce();
        const [url, query, visita] = router.get.mock.calls[0];
        expect(url).toBe('/leads/index');
        expect(query).toEqual({ situacao: 'ativos' });
        expect(visita).toMatchObject({ preserveState: true, preserveScroll: true, replace: true, only: ['leads'] });
    });

    it('ordenarPor alterna asc → desc e inclui sort/dir na query; página e itens por página também', () => {
        const lista = useListagem(opcoes());
        lista.ordenarPor('nome');
        expect(router.get.mock.calls.at(-1)[1]).toEqual({ sort: 'nome', dir: 'asc' });
        lista.ordenarPor('nome');
        expect(router.get.mock.calls.at(-1)[1]).toEqual({ sort: 'nome', dir: 'desc' });
        lista.irParaPagina(2);
        expect(router.get.mock.calls.at(-1)[1]).toEqual({ sort: 'nome', dir: 'desc', page: 2 });
        lista.porPagina(100);
        expect(router.get.mock.calls.at(-1)[1]).toEqual({ sort: 'nome', dir: 'desc', per_page: 100 });
    });

    it('busca tem debounce de 300 ms', async () => {
        const lista = useListagem(opcoes());
        lista.busca.value = 'an';
        lista.busca.value = 'ana';
        await nextTick();
        expect(router.get).not.toHaveBeenCalled();
        vi.advanceTimersByTime(300);
        expect(router.get).toHaveBeenCalledOnce();
        expect(router.get.mock.calls[0][1]).toEqual({ busca: 'ana' });
    });

    it('limpar() remove filtros e busca, mantendo ordenação e itens por página', () => {
        history.replaceState(null, '', '/leads?busca=x&situacao=ativos&sort=nome&dir=asc&per_page=50');
        const lista = useListagem(opcoes());
        lista.limpar();
        expect(router.get.mock.calls.at(-1)[1]).toEqual({ sort: 'nome', dir: 'asc', per_page: 50 });
        expect(lista.filtrosAtivos.value).toBe(0);
    });

    it('carregando acompanha onStart/onFinish e um navigate ressincroniza o estado', () => {
        const lista = useListagem(opcoes());
        lista.aplicar({ situacao: 'ganhos' });
        const visita = router.get.mock.calls[0][2];
        visita.onStart();
        expect(lista.carregando.value).toBe(true);
        visita.onFinish();
        expect(lista.carregando.value).toBe(false);

        history.replaceState(null, '', '/leads?situacao=perdidos&page=2');
        const aoNavegar = router.on.mock.calls.filter((c) => c[0] === 'navigate').at(-1)[1];
        aoNavegar();
        expect(lista.estado.situacao).toBe('perdidos');
        expect(lista.estado.page).toBe(2);
    });
});
