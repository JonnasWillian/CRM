import { computed, getCurrentInstance, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { alternarOrdenacao, contarFiltrosAtivos, lerQuery, montarQuery } from './listagem';

/**
 * Estado de uma grade de página (Inertia) sincronizado com a URL:
 * recarregar e voltar reproduzem a mesma grade. Cada mudança chama
 * router.get com preserveState/preserveScroll/replace e `only`, e o
 * Inertia cancela a visita anterior sozinho.
 *
 *   const lista = useListagem({ rota: 'leads.index', only: ['leads'], padrao: { per_page: 25, sort: 'created_at', dir: 'desc' }, filtros: ['busca', 'estagios', 'situacao'] });
 */
export function useListagem({ rota, params = {}, only = [], padrao = {}, filtros = [], debounce = 300 }) {
    const opcoes = { filtros, padrao };
    const estado = reactive(lerQuery(window.location.search, opcoes));
    const busca = ref(estado.busca ?? '');
    const carregando = ref(false);
    let temporizador = null;

    const query = computed(() => montarQuery(estado, opcoes));
    const filtrosAtivos = computed(() => contarFiltrosAtivos(estado, filtros));

    function visitar() {
        router.get(route(rota, params), montarQuery(estado, opcoes), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only,
            onStart: () => { carregando.value = true; },
            onFinish: () => { carregando.value = false; },
        });
    }

    function aplicar(parcial = {}, { resetarPagina = true } = {}) {
        Object.assign(estado, parcial);
        if (resetarPagina) estado.page = 1;
        visitar();
    }

    const ordenarPor = (coluna) => aplicar(alternarOrdenacao(estado, coluna));
    const irParaPagina = (n) => aplicar({ page: Number(n) || 1 }, { resetarPagina: false });
    const porPagina = (n) => aplicar({ per_page: Number(n) });

    function limpar() {
        const limpo = {};
        for (const f of filtros) limpo[f] = Array.isArray(estado[f]) ? [] : null;
        busca.value = '';
        aplicar({ ...limpo, busca: '' });
    }

    watch(busca, (valor) => {
        clearTimeout(temporizador);
        temporizador = setTimeout(() => {
            if ((valor ?? '') !== (estado.busca ?? '')) aplicar({ busca: valor ?? '' });
        }, debounce);
    });

    // Voltar/avançar: o Inertia navega e a URL muda sem passar por aplicar().
    const removerNavigate = router.on('navigate', () => {
        const lido = lerQuery(window.location.search, opcoes);
        Object.assign(estado, lido);
        busca.value = lido.busca ?? '';
    });

    if (getCurrentInstance()) {
        onBeforeUnmount(() => {
            clearTimeout(temporizador);
            removerNavigate?.();
        });
    }

    return { estado, busca, carregando, query, filtrosAtivos, aplicar, ordenarPor, irParaPagina, porPagina, limpar };
}
