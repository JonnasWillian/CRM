import { ref } from 'vue';

/**
 * Diálogo de confirmação como promessa:
 *
 *   const ok = await confirmar({ titulo: 'Excluir o lead Ana?', mensagem: '…', rotuloConfirmar: 'Excluir lead', tom: 'danger' });
 *
 * Um pedido por vez (estado de módulo); o ConfirmHost do layout renderiza
 * `pendente` e chama `responder(true|false)`. Um pedido novo enquanto outro
 * está aberto resolve o anterior como false.
 */
const pendente = ref(null);

const PADROES = {
    titulo: '',
    mensagem: '',
    detalhe: null,
    rotuloConfirmar: 'Confirmar',
    rotuloCancelar: 'Cancelar',
    tom: 'primary',
    requerDigitacao: null,
};

function confirmar(opcoes) {
    return new Promise((resolve) => {
        if (pendente.value) pendente.value.resolver(false);
        pendente.value = { ...PADROES, ...opcoes, resolver: resolve };
    });
}

function responder(valor) {
    const atual = pendente.value;
    pendente.value = null;
    atual?.resolver(Boolean(valor));
}

export function useConfirm() {
    return { pendente, confirmar, responder };
}
