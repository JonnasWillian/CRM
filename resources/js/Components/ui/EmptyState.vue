<script setup>
defineProps({
    icone: { type: [Object, Function], default: null },
    titulo: { type: String, required: true },
    descricao: { type: String, default: null },
    tamanho: { type: String, default: 'md' }, // sm | md
});
</script>

<template>
    <div class="flex flex-col items-center justify-center text-center" :class="tamanho === 'sm' ? 'px-4 py-8' : 'px-6 py-14'">
        <span v-if="icone" class="mb-3 inline-flex h-11 w-11 items-center justify-center rounded-lg bg-bg-2 text-fg-2" aria-hidden="true">
            <component :is="icone" :size="20" />
        </span>
        <p class="text-md font-semibold text-fg-0">{{ titulo }}</p>
        <p v-if="descricao" class="mt-1 max-w-sm text-sm text-fg-2">{{ descricao }}</p>
        <div v-if="$slots.acao" class="mt-4 flex flex-wrap items-center justify-center gap-2"><slot name="acao" /></div>
    </div>
</template>
