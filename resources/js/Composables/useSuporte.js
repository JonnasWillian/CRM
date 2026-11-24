import { ref } from 'vue';

/**
 * Abre e fecha o assistente de ajuda de qualquer lugar (botão flutuante,
 * menu da conta). Estado de módulo: o assistente é um só no layout.
 */
const aberto = ref(false);

export function useSuporte() {
    return {
        aberto,
        abrir: () => { aberto.value = true; },
        fechar: () => { aberto.value = false; },
    };
}
