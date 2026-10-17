<script setup>
import AppDateInput from './AppDateInput.vue';

/** Intervalo de datas (ISO) com atalhos. */
const props = defineProps({
    de: { type: String, default: null },
    ate: { type: String, default: null },
    presets: { type: Array, default: () => ['7d', '30d'] },
    rotulo: { type: String, default: null },
});

const emit = defineEmits(['update:de', 'update:ate']);

const iso = (d) => d.toISOString().slice(0, 10);

function atalho(preset) {
    const hoje = new Date();
    const inicio = new Date(hoje);
    inicio.setDate(hoje.getDate() - (preset === '7d' ? 7 : 30));
    emit('update:de', iso(inicio));
    emit('update:ate', iso(hoje));
}

const ROTULOS = { '7d': '7 dias', '30d': '30 dias' };
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <span v-if="rotulo" class="mr-1 text-xs font-medium uppercase tracking-wider text-fg-2">{{ rotulo }}</span>
        <span class="w-36"><AppDateInput :model-value="de" tamanho="sm" :aria-label="rotulo ? `${rotulo}: de` : 'De'" @update:model-value="emit('update:de', $event)" /></span>
        <span class="text-sm text-fg-2">a</span>
        <span class="w-36"><AppDateInput :model-value="ate" tamanho="sm" :min="de ?? undefined" :aria-label="rotulo ? `${rotulo}: até` : 'Até'" @update:model-value="emit('update:ate', $event)" /></span>
        <button v-for="p in presets" :key="p" type="button" class="h-8 rounded-full border border-line bg-bg-1 px-3 text-sm text-fg-1 hover:text-fg-0" @click="atalho(p)">
            {{ ROTULOS[p] ?? p }}
        </button>
    </div>
</template>
