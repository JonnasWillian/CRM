import { readonly, ref } from 'vue';

/**
 * Toasts do app inteiro, num estado só (módulo), para o ToastHost do layout
 * renderizar e qualquer página ou interceptor disparar.
 *
 * Durações: sucesso/info 4 s, aviso 6 s, com ação (Desfazer, Abrir) 7 s,
 * erro fica até ser fechado. No máximo 3 visíveis; o resto espera na fila.
 */
const MAX_VISIVEIS = 3;
const DURACAO = { sucesso: 4000, info: 4000, aviso: 6000, erro: null };
const DURACAO_COM_ACAO = 7000;

const toasts = ref([]);
const fila = [];
const temporizadores = new Map();
let proximoId = 1;

function agendar(toast) {
    if (toast.duracao === null) return;
    temporizadores.set(toast.id, setTimeout(() => fechar(toast.id), toast.duracao));
}

function mostrar(toast) {
    toasts.value.push(toast);
    agendar(toast);
}

function emitir(tom, mensagem, opcoes = {}) {
    const { descricao = null, acao = null, duracao } = opcoes;
    const toast = {
        id: proximoId++,
        tom,
        mensagem,
        descricao,
        acao,
        duracao: duracao === undefined ? (acao ? DURACAO_COM_ACAO : DURACAO[tom]) : duracao,
    };

    if (toasts.value.length >= MAX_VISIVEIS) fila.push(toast);
    else mostrar(toast);

    return toast.id;
}

function fechar(id) {
    clearTimeout(temporizadores.get(id));
    temporizadores.delete(id);
    toasts.value = toasts.value.filter((t) => t.id !== id);

    if (fila.length && toasts.value.length < MAX_VISIVEIS) mostrar(fila.shift());
}

function acionar(id) {
    const toast = toasts.value.find((t) => t.id === id);
    toast?.acao?.onClick?.();
    fechar(id);
}

function pausar(id) {
    clearTimeout(temporizadores.get(id));
    temporizadores.delete(id);
}

function retomar(id) {
    const toast = toasts.value.find((t) => t.id === id);
    if (toast && !temporizadores.has(id)) agendar(toast);
}

function limpar() {
    for (const id of temporizadores.keys()) clearTimeout(temporizadores.get(id));
    temporizadores.clear();
    fila.length = 0;
    toasts.value = [];
}

export function useToast() {
    return {
        toasts: readonly(toasts),
        sucesso: (mensagem, opcoes) => emitir('sucesso', mensagem, opcoes),
        erro: (mensagem, opcoes) => emitir('erro', mensagem, opcoes),
        aviso: (mensagem, opcoes) => emitir('aviso', mensagem, opcoes),
        info: (mensagem, opcoes) => emitir('info', mensagem, opcoes),
        fechar,
        acionar,
        pausar,
        retomar,
        limpar,
    };
}
