<script setup>
defineProps({
    titulo: { type: String, required: true },
    descricao: { type: String, default: null },
    contagem: { type: [Number, String], default: null },
});
</script>

<template>
    <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
        <div class="min-w-0">
            <h2 class="flex items-center gap-2 text-lg font-semibold text-fg-0">
                {{ titulo }}
                <span v-if="contagem !== null" class="num rounded-sm bg-bg-2 px-1.5 py-0.5 text-xs font-medium text-fg-1">{{ contagem }}</span>
            </h2>
            <p v-if="descricao" class="mt-0.5 text-sm text-fg-2">{{ descricao }}</p>
        </div>
        <div v-if="$slots.acoes" class="flex shrink-0 items-center gap-2"><slot name="acoes" /></div>
    </div>
</template>
