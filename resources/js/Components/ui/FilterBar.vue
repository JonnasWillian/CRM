<script setup>
import { onBeforeUnmount, ref, watch } from 'vue';
import { Search, SlidersHorizontal, X } from 'lucide-vue-next';

/**
 * Barra de filtros de uma grade: busca com debounce, campos no slot,
 * contador de filtros ativos e "Limpar". Nunca some durante o loading.
 * Abaixo de md os campos ficam atrás de um botão "Filtros (n)".
 */
const props = defineProps({
    busca: { type: String, default: '' },
    placeholderBusca: { type: String, default: 'Buscar' },
    ativos: { type: Number, default: 0 },
    debounce: { type: Number, default: 300 },
});

const emit = defineEmits(['update:busca', 'limpar']);

const texto = ref(props.busca ?? '');
const expandido = ref(false);
let temporizador = null;

watch(() => props.busca, (v) => { if ((v ?? '') !== texto.value) texto.value = v ?? ''; });

function aoDigitar(e) {
    texto.value = e.target.value;
    clearTimeout(temporizador);
    temporizador = setTimeout(() => emit('update:busca', texto.value), props.debounce);
}

function limparBusca() {
    texto.value = '';
    clearTimeout(temporizador);
    emit('update:busca', '');
}

onBeforeUnmount(() => clearTimeout(temporizador));
</script>

<template>
    <div class="flex flex-col gap-3">
        <div class="flex items-center gap-2">
            <label class="relative flex-1 sm:max-w-sm">
                <span class="sr-only">{{ placeholderBusca }}</span>
                <Search :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-fg-2" aria-hidden="true" />
                <input
                    type="search"
                    :value="texto"
                    :placeholder="placeholderBusca"
                    class="form-input h-9 w-full rounded border-line bg-bg-1 pl-9 pr-9 text-md text-fg-0 placeholder:text-fg-disabled focus:border-focus focus:ring-2 focus:ring-focus/25"
                    @input="aoDigitar"
                />
                <button v-if="texto" type="button" aria-label="Limpar busca" class="absolute right-2 top-1/2 inline-flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded text-fg-2 hover:bg-bg-2 hover:text-fg-0" @click="limparBusca">
                    <X :size="14" aria-hidden="true" />
                </button>
            </label>
            <button
                v-if="$slots.default"
                type="button"
                class="inline-flex h-9 items-center gap-2 rounded border border-line bg-bg-1 px-3 text-sm font-medium text-fg-1 md:hidden"
                :aria-expanded="expandido ? 'true' : 'false'"
                @click="expandido = !expandido"
            >
                <SlidersHorizontal :size="16" aria-hidden="true" /> Filtros<span v-if="ativos" class="num">({{ ativos }})</span>
            </button>
            <button v-if="ativos > 0" type="button" class="inline-flex h-9 items-center gap-1 whitespace-nowrap rounded px-2.5 text-sm font-medium text-primary hover:bg-primary-subtle" @click="emit('limpar')">
                <X :size="14" aria-hidden="true" /> Limpar (<span class="num">{{ ativos }}</span>)
            </button>
            <div v-if="$slots.direita" class="ml-auto flex items-center gap-2"><slot name="direita" /></div>
        </div>
        <div v-if="$slots.default" class="flex-col gap-2.5 md:flex md:flex-row md:flex-wrap md:items-center" :class="expandido ? 'flex' : 'hidden'">
            <slot />
        </div>
    </div>
</template>
