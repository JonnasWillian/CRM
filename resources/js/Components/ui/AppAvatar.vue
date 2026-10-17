<script setup>
import { computed } from 'vue';
import { iniciais } from '@/utils/formatadores';

const props = defineProps({
    nome: { type: String, default: '' },
    tamanho: { type: Number, default: 28 }, // 24 · 28 · 32 · 40
});

const TAMANHOS = {
    24: 'h-6 w-6 text-[10px]',
    28: 'h-7 w-7 text-[11px]',
    32: 'h-8 w-8 text-xs',
    40: 'h-10 w-10 text-sm',
};

const classes = computed(() => TAMANHOS[props.tamanho] ?? TAMANHOS[28]);
</script>

<template>
    <span
        role="img"
        :aria-label="nome || 'Sem nome'"
        class="inline-flex shrink-0 select-none items-center justify-center rounded-sm bg-primary-subtle font-display font-bold text-primary"
        :class="classes"
    >
        {{ iniciais(nome) }}
    </span>
</template>
