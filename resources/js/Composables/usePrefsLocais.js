import { ref, watch } from 'vue';

/**
 * Preferência por navegador (coluna oculta, densidade, sidebar colapsada)
 * em localStorage, com prefixo `ui.`. Nunca guarda dado de negócio: pode
 * sumir numa janela anônima ou com o storage bloqueado, e a tela tem de
 * continuar funcionando — por isso todo acesso está em try/catch.
 */
export function usePrefsLocais(chave, padrao) {
    const nome = `ui.${chave}`;

    const ler = () => {
        try {
            const bruto = localStorage.getItem(nome);
            return bruto === null ? clonar(padrao) : JSON.parse(bruto);
        } catch {
            return clonar(padrao);
        }
    };

    const valor = ref(ler());

    watch(
        valor,
        (novo) => {
            try {
                localStorage.setItem(nome, JSON.stringify(novo));
            } catch {
                /* storage indisponível: a preferência vale só nesta sessão */
            }
        },
        { deep: true },
    );

    return valor;
}

function clonar(v) {
    return typeof v === 'object' && v !== null ? JSON.parse(JSON.stringify(v)) : v;
}
