/**
 * Leitura uniforme de erros do axios.
 *
 * O backend responde 422 de validação como { message, errors } (padrão do
 * framework); durante a migração alguns endpoints ainda devolvem { erros }.
 * Regra de domínio (funil sem estágio aberto, motivo obrigatório…) vem como
 * { error } ou { message } com a frase pronta para o usuário.
 */

const MENSAGENS_GENERICAS = new Set(['The given data was invalid.', 'Os dados fornecidos são inválidos.']);

export function errosDeValidacao(e) {
    const data = e?.response?.data;
    const campos = data?.errors ?? data?.erros;
    return campos && typeof campos === 'object' ? campos : {};
}

export function primeiroErro(campos) {
    for (const mensagens of Object.values(campos ?? {})) {
        const lista = Array.isArray(mensagens) ? mensagens : [mensagens];
        if (lista.length && lista[0]) return String(lista[0]);
    }
    return null;
}

export function ehErroDeValidacao(e) {
    return e?.response?.status === 422 && Object.keys(errosDeValidacao(e)).length > 0;
}

export function mensagemDeErro(e, padrao = 'Não foi possível concluir. Tente novamente.') {
    const data = e?.response?.data;
    if (!data || typeof data !== 'object') return padrao;

    if (typeof data.error === 'string' && data.error) return data.error;

    const deCampo = primeiroErro(errosDeValidacao(e));
    if (deCampo) return deCampo;

    if (typeof data.message === 'string' && data.message && !MENSAGENS_GENERICAS.has(data.message)) {
        return data.message;
    }

    return padrao;
}
