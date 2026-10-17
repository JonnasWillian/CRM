<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { X } from 'lucide-vue-next';
import IconButton from './IconButton.vue';

/**
 * Modal sobre <dialog> nativo: role, foco preso e camada superior vêm do
 * navegador. Aqui ficam o que ele não dá: foco inicial, retorno do foco a
 * quem abriu, fechar no fundo, e virar folha colada embaixo em telas
 * estreitas.
 */
const props = defineProps({
    aberto: { type: Boolean, default: false },
    titulo: { type: String, required: true },
    descricao: { type: String, default: null },
    tamanho: { type: String, default: 'md' }, // sm 400 · md 560 · lg 720 · xl 960
    persistente: { type: Boolean, default: false }, // não fecha no fundo
    fechavel: { type: Boolean, default: true }, // Esc e botão X
});

const emit = defineEmits(['fechar']);

const dialog = ref(null);
const conteudo = ref(null);
const rodape = ref(null);
const idTitulo = `modal-titulo-${useId()}`;
const idDescricao = `modal-desc-${useId()}`;
let anterior = null;

const TAMANHOS = { sm: 'sm:max-w-[400px]', md: 'sm:max-w-[560px]', lg: 'sm:max-w-[720px]', xl: 'sm:max-w-[960px]' };

function abrirNativo() {
    const el = dialog.value;
    if (!el) return;
    if (typeof el.showModal === 'function') {
        if (!el.open) el.showModal();
    } else {
        el.setAttribute('open', '');
    }
    document.body.style.overflow = 'hidden';
}

function fecharNativo() {
    const el = dialog.value;
    if (el) {
        if (typeof el.close === 'function' && el.open) el.close();
        else el.removeAttribute('open');
    }
    document.body.style.overflow = '';
}

const FOCAVEL = 'input:not([type=hidden]), select, textarea, button, [href], [tabindex]:not([tabindex="-1"])';

// Ordem: quem pediu (autofocus) → conteúdo → rodapé → botão de fechar.
// O X de fechar vem por último de propósito: Enter por reflexo não pode
// fechar um formulário recém-aberto.
function focarPrimeiro() {
    const el = dialog.value;
    if (!el) return;
    const alvo =
        el.querySelector('[autofocus]') ??
        conteudo.value?.querySelector(FOCAVEL) ??
        rodape.value?.querySelector(FOCAVEL) ??
        el.querySelector(FOCAVEL);
    alvo?.focus();
}

function pedirFechar() {
    if (props.fechavel) emit('fechar');
}

function aoClicar(e) {
    if (e.target === dialog.value && !props.persistente) pedirFechar();
}

onMounted(() => {
    if (props.aberto) {
        anterior = document.activeElement;
        abrirNativo();
        nextTick(focarPrimeiro);
    }
});

watch(
    () => props.aberto,
    async (agora) => {
        if (agora) {
            anterior = document.activeElement;
            await nextTick();
            abrirNativo();
            focarPrimeiro();
        } else {
            fecharNativo();
            anterior?.focus?.();
            anterior = null;
        }
    },
);

onBeforeUnmount(() => {
    if (props.aberto) fecharNativo();
});
</script>

<template>
    <dialog
        v-if="aberto"
        ref="dialog"
        :aria-labelledby="idTitulo"
        :aria-describedby="descricao ? idDescricao : undefined"
        class="modal fixed inset-x-0 bottom-0 m-0 w-full max-h-[92dvh] overflow-hidden rounded-t-lg border border-line bg-bg-1 p-0 text-fg-0 shadow-3 backdrop:bg-fg-0/40 sm:inset-0 sm:m-auto sm:rounded-lg"
        :class="TAMANHOS[tamanho] ?? TAMANHOS.md"
        @click="aoClicar"
        @keydown.escape.prevent="pedirFechar"
        @cancel.prevent="pedirFechar"
    >
        <div class="flex max-h-[92dvh] flex-col">
            <header class="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
                <div class="min-w-0">
                    <h2 :id="idTitulo" class="font-display text-xl font-semibold">{{ titulo }}</h2>
                    <p v-if="descricao" :id="idDescricao" class="mt-1 text-sm text-fg-2">{{ descricao }}</p>
                </div>
                <IconButton v-if="fechavel" :icone="X" rotulo="Fechar" tamanho="sm" @click="pedirFechar" />
            </header>
            <div ref="conteudo" class="min-h-0 flex-1 overflow-y-auto px-5 py-4"><slot /></div>
            <footer v-if="$slots.rodape" ref="rodape" class="flex flex-wrap items-center justify-end gap-2 border-t border-line bg-bg-0/60 px-5 py-3"><slot name="rodape" /></footer>
        </div>
    </dialog>
</template>

<style scoped>
.modal[open] {
    animation: modal-entra var(--d-2) var(--e) both;
}
@keyframes modal-entra {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: none; }
}
</style>
