<script setup>
import { computed } from 'vue';

const props = defineProps({
    valor: { type: Number, default: 0 }, // 0–100
    rotulo: { type: String, required: true },
    tamanho: { type: Number, default: 96 },
    espessura: { type: Number, default: 8 },
});

const limitado = computed(() => Math.max(0, Math.min(100, Math.round(props.valor || 0))));
const raio = computed(() => (props.tamanho - props.espessura) / 2);
const circunferencia = computed(() => 2 * Math.PI * raio.value);
const traco = computed(() => `${(limitado.value / 100) * circunferencia.value} ${circunferencia.value}`);
</script>

<template>
    <div role="img" :aria-label="`${rotulo}: ${limitado}%`" class="relative inline-flex items-center justify-center" :style="{ width: `${tamanho}px`, height: `${tamanho}px` }">
        <svg :width="tamanho" :height="tamanho" :viewBox="`0 0 ${tamanho} ${tamanho}`" class="-rotate-90" aria-hidden="true">
            <circle :cx="tamanho / 2" :cy="tamanho / 2" :r="raio" fill="none" class="stroke-bg-2" :stroke-width="espessura" />
            <circle
                :cx="tamanho / 2"
                :cy="tamanho / 2"
                :r="raio"
                fill="none"
                class="stroke-primary transition-[stroke-dasharray] duration-3 ease-ui"
                :stroke-width="espessura"
                stroke-linecap="round"
                :stroke-dasharray="traco"
            />
        </svg>
        <span class="num absolute font-display text-lg font-semibold text-fg-0">{{ limitado }}%</span>
    </div>
</template>
