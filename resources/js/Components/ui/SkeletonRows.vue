<script setup>
import AppSkeleton from './AppSkeleton.vue';

defineProps({ linhas: { type: Number, default: 6 }, colunas: { type: Number, default: 4 } });

// Larguras fixas por coluna para o esqueleto não "dançar" entre renders.
const LARGURAS = ['60%', '40%', '75%', '30%', '50%', '45%'];
</script>

<template>
    <div aria-hidden="true" class="divide-y divide-line">
        <div v-for="l in linhas" :key="l" class="flex items-center gap-4 px-3 py-3">
            <AppSkeleton v-for="c in colunas" :key="c" :largura="LARGURAS[(c - 1) % LARGURAS.length]" class="flex-1" />
        </div>
    </div>
</template>
