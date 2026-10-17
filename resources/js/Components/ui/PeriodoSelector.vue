<script setup>
import AppDateInput from './AppDateInput.vue';

/**
 * Seletor de período das métricas: presets do backend (Periodo) e
 * personalizado com de/até. v-model = { periodo, de, ate }.
 */
const props = defineProps({
    modelValue: { type: Object, required: true }, // { periodo: '7d'|'30d'|'mes_atual'|'mes_anterior'|'custom', de, ate }
});

const emit = defineEmits(['update:modelValue']);

const PRESETS = [
    { chave: '7d', rotulo: '7 dias' },
    { chave: '30d', rotulo: '30 dias' },
    { chave: 'mes_atual', rotulo: 'Mês atual' },
    { chave: 'mes_anterior', rotulo: 'Mês anterior' },
    { chave: 'custom', rotulo: 'Personalizado' },
];

const escolher = (chave) => emit('update:modelValue', { periodo: chave, de: chave === 'custom' ? props.modelValue.de : null, ate: chave === 'custom' ? props.modelValue.ate : null });
const data = (campo, valor) => emit('update:modelValue', { ...props.modelValue, periodo: 'custom', [campo]: valor });
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <div role="radiogroup" aria-label="Período" class="inline-flex rounded border border-line bg-bg-1 p-0.5">
            <button
                v-for="p in PRESETS"
                :key="p.chave"
                type="button"
                :aria-pressed="modelValue.periodo === p.chave ? 'true' : 'false'"
                class="h-8 whitespace-nowrap rounded-sm px-2.5 text-sm font-medium transition-colors duration-1 ease-ui"
                :class="modelValue.periodo === p.chave ? 'bg-primary-subtle text-primary' : 'text-fg-1 hover:text-fg-0'"
                @click="escolher(p.chave)"
            >
                {{ p.rotulo }}
            </button>
        </div>
        <template v-if="modelValue.periodo === 'custom'">
            <AppDateInput :model-value="modelValue.de" tamanho="sm" aria-label="De" @update:model-value="data('de', $event)" />
            <span class="text-sm text-fg-2">a</span>
            <AppDateInput :model-value="modelValue.ate" tamanho="sm" :min="modelValue.de ?? undefined" aria-label="Até" @update:model-value="data('ate', $event)" />
        </template>
    </div>
</template>
