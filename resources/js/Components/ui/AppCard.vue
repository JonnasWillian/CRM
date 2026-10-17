<script setup>
defineProps({
    padding: { type: String, default: 'md' }, // none | sm | md
    as: { type: String, default: 'section' },
});

const PADDING = { none: '', sm: 'p-3', md: 'p-4 md:p-5' };
</script>

<template>
    <component :is="as" class="rounded-lg border border-line bg-bg-1 shadow-1">
        <div v-if="$slots.cabecalho" class="border-b border-line px-4 py-3 md:px-5"><slot name="cabecalho" /></div>
        <div :class="PADDING[padding] ?? PADDING.md"><slot /></div>
    </component>
</template>
