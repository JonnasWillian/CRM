<script setup>
defineProps({
    variante: { type: String, default: 'text' }, // text | rect | circle
    largura: { type: String, default: '100%' },
    altura: { type: String, default: null },
});
</script>

<template>
    <span
        aria-hidden="true"
        class="block animate-pulse bg-bg-2"
        :class="variante === 'circle' ? 'rounded-full' : 'rounded-sm'"
        :style="{ width: largura, height: altura ?? (variante === 'text' ? '0.875em' : '2rem') }"
    />
</template>
