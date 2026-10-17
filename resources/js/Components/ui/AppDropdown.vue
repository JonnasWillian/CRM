<script setup>
import { nextTick, onBeforeUnmount, provide, ref, useId } from 'vue';
import { router } from '@inertiajs/vue3';
import { MoreHorizontal } from 'lucide-vue-next';
import { posicionar } from '@/utils/posicionar';

/**
 * Menu de ações. O gatilho padrão é um botão de ícone (⋯) com aria-label =
 * `rotulo`; o slot `gatilho` recebe { alternar, aberto, atributos } para um
 * gatilho próprio. O menu vai para o <body> (Teleport), tem role=menu,
 * setas/Home/End, Esc devolve o foco ao gatilho, clique fora fecha, e
 * navegar fecha.
 */
const props = defineProps({
    rotulo: { type: String, default: 'Mais ações' },
    icone: { type: [Object, Function], default: () => MoreHorizontal },
    alinhar: { type: String, default: 'end' }, // start | end
    lado: { type: String, default: 'bottom' },
    tamanho: { type: String, default: 'md' },
});

const aberto = ref(false);
const invólucro = ref(null);
const menu = ref(null);
const estilo = ref({});
const idMenu = `menu-${useId()}`;

const TAMANHOS = { sm: 'h-8 w-8', md: 'h-9 w-9' };

function gatilhoEl() {
    return invólucro.value?.querySelector('button, a, [tabindex]') ?? invólucro.value;
}

function itens() {
    return [...(menu.value?.querySelectorAll('[role="menuitem"]:not([disabled]):not([aria-disabled="true"])') ?? [])];
}

async function abrir() {
    if (aberto.value) return;
    aberto.value = true;
    await nextTick();
    const g = gatilhoEl()?.getBoundingClientRect();
    const m = menu.value?.getBoundingClientRect();
    if (g && m) {
        const pos = posicionar(g, { width: m.width, height: m.height }, { lado: props.lado, alinhar: props.alinhar, margem: 4 });
        estilo.value = { top: `${pos.top}px`, left: `${pos.left}px` };
    }
    itens()[0]?.focus();
    document.addEventListener('mousedown', aoClicarFora, true);
}

function fechar(devolverFoco = true) {
    if (!aberto.value) return;
    aberto.value = false;
    document.removeEventListener('mousedown', aoClicarFora, true);
    if (devolverFoco) gatilhoEl()?.focus();
}

const alternar = () => (aberto.value ? fechar() : abrir());

function aoClicarFora(e) {
    if (menu.value?.contains(e.target) || invólucro.value?.contains(e.target)) return;
    fechar(false);
}

function aoTeclar(e) {
    const lista = itens();
    const i = lista.indexOf(document.activeElement);
    const focar = (n) => lista[(n + lista.length) % lista.length]?.focus();

    switch (e.key) {
        case 'ArrowDown': e.preventDefault(); focar(i + 1); break;
        case 'ArrowUp': e.preventDefault(); focar(i - 1); break;
        case 'Home': e.preventDefault(); focar(0); break;
        case 'End': e.preventDefault(); focar(lista.length - 1); break;
        case 'Escape': e.preventDefault(); fechar(); break;
        case 'Tab': fechar(false); break;
    }
}

const removerNavegacao = router.on('navigate', () => fechar(false));

onBeforeUnmount(() => {
    fechar(false);
    removerNavegacao();
});

provide('dropdown', { fechar });

const atributos = () => ({ 'aria-haspopup': 'menu', 'aria-expanded': aberto.value ? 'true' : 'false', 'aria-controls': aberto.value ? idMenu : undefined });
</script>

<template>
    <span ref="invólucro" class="inline-flex">
        <slot name="gatilho" :alternar="alternar" :aberto="aberto" :atributos="atributos()">
            <button
                type="button"
                v-bind="atributos()"
                :aria-label="rotulo"
                class="inline-flex shrink-0 items-center justify-center rounded text-fg-1 transition-colors duration-1 ease-ui hover:bg-bg-2 hover:text-fg-0"
                :class="[TAMANHOS[tamanho] ?? TAMANHOS.md, aberto ? 'bg-bg-2 text-fg-0' : '']"
                @click="alternar"
            >
                <component :is="icone" :size="16" aria-hidden="true" />
            </button>
        </slot>
    </span>
    <Teleport to="body">
        <div
            v-if="aberto"
            :id="idMenu"
            ref="menu"
            role="menu"
            :aria-label="rotulo"
            :style="estilo"
            class="fixed z-[60] min-w-[180px] max-w-[280px] rounded-lg border border-line bg-bg-1 p-1 shadow-2"
            @keydown="aoTeclar"
        >
            <slot />
        </div>
    </Teleport>
</template>
