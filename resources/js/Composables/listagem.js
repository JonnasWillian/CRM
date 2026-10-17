/**
 * Estado de uma listagem paginada (page, per_page, sort, dir, busca e os
 * filtros da tela), compartilhado por useListagem (Inertia, URL) e
 * useListagemAxios (painéis). Puro: nada de Vue aqui.
 */
export const CHAVES_BASE = ['page', 'per_page', 'sort', 'dir', 'busca'];

const numero = (v) => (v !== '' && v !== null && v !== undefined && !Number.isNaN(Number(v)) && /^-?\d+(\.\d+)?$/.test(String(v)) ? Number(v) : v);

/** Lê page, per_page, sort, dir, busca e os filtros de uma query string. Arrays vêm como `chave[]`. */
export function lerQuery(search, { filtros = [], padrao = {} } = {}) {
    const params = new URLSearchParams(search ?? '');
    const estado = { page: 1, per_page: padrao.per_page ?? 25, sort: padrao.sort ?? null, dir: padrao.dir ?? null, busca: '' };

    for (const chave of [...CHAVES_BASE, ...filtros]) {
        if (params.has(`${chave}[]`)) {
            estado[chave] = params.getAll(`${chave}[]`).map(numero);
        } else if (params.has(chave)) {
            const bruto = params.get(chave);
            estado[chave] = chave === 'busca' ? bruto : chave === 'page' || chave === 'per_page' ? Number(bruto) || estado[chave] : numero(bruto);
        } else if (!(chave in estado)) {
            estado[chave] = padrao[chave] ?? null;
        }
    }

    return estado;
}

const vazio = (v) => v === null || v === undefined || v === '' || v === false || (Array.isArray(v) && v.length === 0);

/** Monta a query sem os valores iguais ao padrão (URL limpa). */
export function montarQuery(estado, { filtros = [], padrao = {} } = {}) {
    const query = {};
    const padroes = { page: 1, per_page: padrao.per_page ?? 25, sort: padrao.sort ?? null, dir: padrao.dir ?? null, busca: '' };

    for (const chave of [...CHAVES_BASE, ...filtros]) {
        const valor = estado[chave];
        const base = chave in padroes ? padroes[chave] : (padrao[chave] ?? null);
        if (vazio(valor) || valor === base) continue;
        if (chave === 'dir' && estado.sort === padroes.sort && valor === padroes.dir) continue;
        query[chave] = valor;
    }

    // dir sem sort não faz sentido; sort padrão com dir padrão já foi omitido acima.
    if (query.dir && !query.sort && estado.sort) query.sort = estado.sort;
    if (query.sort && !query.dir && estado.dir) query.dir = estado.dir;

    return query;
}

/** Alterna a ordenação: mesma coluna inverte; outra coluna começa em asc. */
export function alternarOrdenacao(estado, coluna) {
    if (estado.sort === coluna) return { sort: coluna, dir: estado.dir === 'asc' ? 'desc' : 'asc' };
    return { sort: coluna, dir: 'asc' };
}

export function contarFiltrosAtivos(estado, filtros) {
    return filtros.filter((f) => f !== 'busca' && !vazio(estado[f])).length;
}
