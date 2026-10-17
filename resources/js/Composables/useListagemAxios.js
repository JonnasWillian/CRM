import { computed, reactive, ref, watch } from 'vue';
import axios from 'axios';
import { mensagemDeErro } from '@/utils/erros';
import { alternarOrdenacao, contarFiltrosAtivos, montarQuery } from './listagem';

/**
 * A mesma superfície de useListagem para painéis carregados por axios
 * (abas do lead): linhas, meta, ordenação, página. Cada carga cancela a
 * anterior (AbortController), e a chamada é `silenciosa` para o
 * interceptor — o painel mostra o erro inline com "Tentar de novo".
 *
 * Aceita resposta `{ data, meta, sort }` (contrato de listagem) ou um array
 * simples (meta = null, sem paginação).
 */
export function useListagemAxios(url, { padrao = {}, filtros = [], params = {}, imediato = true, debounce = 300 } = {}) {
    const opcoes = { filtros, padrao };
    const estado = reactive({ page: 1, per_page: padrao.per_page ?? 25, sort: padrao.sort ?? null, dir: padrao.dir ?? null, busca: '', ...Object.fromEntries(filtros.map((f) => [f, padrao[f] ?? null])) });
    const busca = ref('');
    const linhas = ref([]);
    const meta = ref(null);
    const sortDoServidor = ref(null);
    const carregando = ref(false);
    const erro = ref(null);
    let controlador = null;
    let temporizador = null;

    const ordenacao = computed(() => sortDoServidor.value ?? (estado.sort ? { por: estado.sort, dir: estado.dir ?? 'asc' } : null));
    const filtrosAtivos = computed(() => contarFiltrosAtivos(estado, filtros));

    async function carregar() {
        controlador?.abort();
        controlador = new AbortController();
        const { signal } = controlador;
        carregando.value = true;
        erro.value = null;

        try {
            const resposta = await axios.get(typeof url === 'function' ? url() : url, {
                params: { ...params, ...montarQuery(estado, opcoes) },
                signal,
                silencioso: true,
            });
            if (signal.aborted) return;
            const corpo = resposta.data;
            if (Array.isArray(corpo)) {
                linhas.value = corpo;
                meta.value = null;
                sortDoServidor.value = null;
            } else {
                linhas.value = corpo.data ?? [];
                meta.value = corpo.meta ?? null;
                sortDoServidor.value = corpo.sort ?? null;
            }
        } catch (e) {
            if (signal.aborted || axios.isCancel?.(e) || e?.name === 'CanceledError') return;
            erro.value = mensagemDeErro(e);
        } finally {
            if (!signal.aborted) carregando.value = false;
        }
    }

    function aplicar(parcial = {}, { resetarPagina = true } = {}) {
        Object.assign(estado, parcial);
        if (resetarPagina) estado.page = 1;
        return carregar();
    }

    const ordenarPor = (coluna) => aplicar(alternarOrdenacao(estado, coluna));
    const irParaPagina = (n) => aplicar({ page: Number(n) || 1 }, { resetarPagina: false });
    const porPagina = (n) => aplicar({ per_page: Number(n) });
    const recarregar = () => carregar();

    function limpar() {
        const limpo = {};
        for (const f of filtros) limpo[f] = Array.isArray(estado[f]) ? [] : null;
        busca.value = '';
        return aplicar({ ...limpo, busca: '' });
    }

    watch(busca, (valor) => {
        clearTimeout(temporizador);
        temporizador = setTimeout(() => {
            if ((valor ?? '') !== (estado.busca ?? '')) aplicar({ busca: valor ?? '' });
        }, debounce);
    });

    if (imediato) carregar();

    return { estado, busca, linhas, meta, ordenacao, carregando, erro, filtrosAtivos, aplicar, ordenarPor, irParaPagina, porPagina, recarregar, limpar };
}
