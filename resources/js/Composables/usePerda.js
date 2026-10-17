import { ref } from 'vue';
import { mensagemDeErro } from '@/utils/erros';

/**
 * Entrar em perda exige motivo (seis caminhos; o backend recusa todos sem
 * ele). Um modal só, hospedado no layout (PerdaHost), para o app inteiro:
 *
 *   const ok = await pedirPerda({ titulo: 'Perder “Ana”', enviar: (perda) => axios.patch(url, { ...payload, perda }) });
 *   if (!ok) desfazer();
 *
 * `enviar` recebe { motivo_perda_id, observacao }. Erro do backend mantém o
 * modal aberto com a mensagem; cancelar resolve false para quem chamou
 * desfazer o que já tinha mudado na tela (o card arrastado, por exemplo).
 */
const pendente = ref(null);

export function usePerda() {
    function pedirPerda({ titulo = 'Registrar perda', enviar, detalhe = null }) {
        return new Promise((resolve) => {
            if (pendente.value) pendente.value.resolver(false);
            pendente.value = { titulo, detalhe, enviar, erro: '', salvando: false, resolver: resolve };
        });
    }

    async function confirmar(perda) {
        const atual = pendente.value;
        if (!atual) return;
        atual.salvando = true;
        atual.erro = '';
        try {
            await atual.enviar(perda);
            pendente.value = null;
            atual.resolver(true);
        } catch (e) {
            atual.salvando = false;
            atual.erro = mensagemDeErro(e, 'Não foi possível registrar a perda.');
        }
    }

    function cancelar() {
        const atual = pendente.value;
        pendente.value = null;
        atual?.resolver(false);
    }

    return { pendente, pedirPerda, confirmar, cancelar };
}
