<script setup>
import { nextTick, onBeforeUnmount, ref, useId } from 'vue';
import { posicionar } from '@/utils/posicionar';

/**
 * Dica de texto para botões de ícone e abreviações. Aparece ao passar o
 * mouse e ao receber foco pelo teclado; vai para o <body> (Teleport) para
 * não ser cortada pelo overflow de tabelas; liga o gatilho por
 * aria-describedby. O atraso visual é CSS (transition-delay), então o nó
 * existe no DOM na hora — leitor de tela e teste o encontram.
 */
const props = defineProps({
    texto: { type: String, required: true },
    lado: { type: String, default: 'top' },
});

const id = `dica-${useId()}`;
const aberto = ref(false);
const invólucro = ref(null);
const painel = ref(null);
const estilo = ref({});

function gatilho() {
    return invólucro.value?.firstElementChild ?? invólucro.value;
}

async function abrir() {
    if (aberto.value) return;
    aberto.value = true;
    gatilho()?.setAttribute('aria-describedby', id);
    await nextTick();
    if (!painel.value || !gatilho()) return;
    const g = gatilho().getBoundingClientRect();
    const p = painel.value.getBoundingClientRect();
    const pos = posicionar(g, { width: p.width, height: p.height }, { lado: props.lado, alinhar: 'center', margem: 8 });
    estilo.value = { top: `${pos.top}px`, left: `${pos.left}px` };
}

function fechar() {
    if (!aberto.value) return;
    aberto.value = false;
    gatilho()?.removeAttribute('aria-describedby');
}

onBeforeUnmount(fechar);
</script>

<template>
    <span ref="invólucro" class="inline-flex" @mouseover="abrir" @mouseout="fechar" @focusin="abrir" @focusout="fechar">
        <slot />
    </span>
    <Teleport to="body">
        <span
            v-if="aberto"
            :id="id"
            ref="painel"
            role="tooltip"
            :style="estilo"
            class="dica pointer-events-none fixed z-[70] max-w-xs rounded-sm bg-fg-0 px-2 py-1 text-xs font-medium text-bg-1 shadow-2"
        >
            {{ texto }}
        </span>
    </Teleport>
</template>

<style scoped>
.dica {
    animation: dica-entra var(--d-1) var(--e) 300ms both;
}
@keyframes dica-entra {
    from { opacity: 0; transform: translateY(2px); }
    to   { opacity: 1; transform: none; }
}
</style>
