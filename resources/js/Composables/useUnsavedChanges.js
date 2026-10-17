import { router } from '@inertiajs/vue3';
import { getCurrentInstance, onBeforeUnmount } from 'vue';
import { useConfirm } from './useConfirm';

/**
 * Guarda de "alterações não salvas": segura a navegação do Inertia e o
 * fechamento da aba enquanto `estaSujo()` for true, pergunta, e só então
 * retoma a visita.
 *
 *   const guarda = useUnsavedChanges(() => form.isDirty);
 *   …depois de salvar: guarda.liberar();
 */
export function useUnsavedChanges(estaSujo, opcoes = {}) {
    const { confirmar } = opcoes.confirmar ? opcoes : useConfirm();
    let ignorar = false;

    const aoNavegar = (evento) => {
        if (ignorar || !estaSujo()) return;

        evento.preventDefault();
        const visita = evento.detail.visit;

        confirmar({
            titulo: 'Sair sem salvar?',
            mensagem: 'Você alterou os dados e ainda não salvou. Se sair agora, as alterações se perdem.',
            rotuloConfirmar: 'Sair sem salvar',
            rotuloCancelar: 'Continuar editando',
            tom: 'danger',
        }).then((ok) => {
            if (!ok) return;
            ignorar = true;
            const url = typeof visita.url === 'string' ? visita.url : visita.url.href;
            router.visit(url, { method: visita.method, data: visita.data });
        });
    };

    const aoFechar = (evento) => {
        if (ignorar || !estaSujo()) return;
        evento.preventDefault();
        evento.returnValue = '';
    };

    const removerBefore = router.on('before', aoNavegar);
    window.addEventListener('beforeunload', aoFechar);

    const parar = () => {
        removerBefore();
        window.removeEventListener('beforeunload', aoFechar);
    };

    if (getCurrentInstance()) onBeforeUnmount(parar);

    return {
        liberar: () => { ignorar = true; },
        rearmar: () => { ignorar = false; },
        parar,
    };
}
