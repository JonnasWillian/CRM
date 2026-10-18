import { computed, ref } from 'vue';

/**
 * Tema da interface: 'claro', 'escuro' ou 'sistema' (segue o sistema
 * operacional). A escolha fica no navegador (`ui.tema`) e o tema efetivo
 * vai para <html data-theme>, que o theme.css lê. O <head> aplica o mesmo
 * cálculo antes da primeira pintura (app.blade.php), para não piscar claro.
 *
 * Estado de módulo: todas as telas compartilham a mesma escolha.
 */
const OPCOES = ['claro', 'escuro', 'sistema'];
const CHAVE = 'ui.tema';

function lerPreferencia() {
    try {
        const v = localStorage.getItem(CHAVE);
        return OPCOES.includes(v) ? v : 'sistema';
    } catch {
        return 'sistema';
    }
}

const consulta = typeof window !== 'undefined' && window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
const preferencia = ref(lerPreferencia());
const sistemaEscuro = ref(Boolean(consulta?.matches));
consulta?.addEventListener?.('change', (e) => {
    sistemaEscuro.value = e.matches;
    aplicar();
});

const aplicado = computed(() => (preferencia.value === 'sistema' ? (sistemaEscuro.value ? 'escuro' : 'claro') : preferencia.value));

function aplicar() {
    if (typeof document !== 'undefined') document.documentElement.dataset.theme = aplicado.value;
}
aplicar();

export function useTema() {
    function escolher(opcao) {
        if (!OPCOES.includes(opcao)) return;
        preferencia.value = opcao;
        try {
            localStorage.setItem(CHAVE, opcao);
        } catch {
            /* storage indisponível: vale só nesta sessão */
        }
        aplicar();
    }

    return { preferencia, aplicado, escolher, opcoes: OPCOES };
}
