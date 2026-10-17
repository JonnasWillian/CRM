<script setup>
import AppDateInput from './AppDateInput.vue';

/** Intervalo de datas (ISO) com atalhos. */
const props = defineProps({
    de: { type: String, default: null },
    ate: { type: String, default: null },
    presets: { type: Array, default: () => ['7d', '30d'] },
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
        <AppDateInput :model-value="de" tamanho="sm" aria-label="De" @update:model-value="emit('update:de', $event)" />
        <span class="text-sm text-fg-2">a</span>
        <AppDateInput :model-value="ate" tamanho="sm" :min="de ?? undefined" aria-label="Até" @update:model-value="emit('update:ate', $event)" />
        <button v-for="p in presets" :key="p" type="button" class="h-8 rounded-full border border-line bg-bg-1 px-3 text-sm text-fg-1 hover:text-fg-0" @click="atalho(p)">
            {{ ROTULOS[p] ?? p }}
        </button>
    </div>
</template>
